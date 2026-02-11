<?php

namespace App\Service;

use App\Dto\ArticleDto;
use App\Dto\SimilarArticle;
use App\Dto\SimilarArticleWithScore;
use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Repository\SimilarArticleRepository;
use Psr\Log\LoggerInterface;

class ArticleVerifier
{
    public function __construct(
        private readonly ArticleWebSearch $articleWebSearch,
        private readonly SimilarityAnalyzer $similarityAnalyzer,
        private readonly ArticleRepository $articleRepository,
        private readonly SimilarArticleRepository $similarArticleRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Verify an article by searching for similar articles and scoring them
     *
     * @return SimilarArticleWithScore[]
     */
    public function verify(ArticleDto $articleDto): array
    {
        try {
            $this->logger->info('Verifying article', [
                'articleId' => $articleDto->articleId->toRfc4122(),
                'title' => $articleDto->title,
            ]);

            $similarArticles = $this->articleWebSearch->searchSimilarArticles($articleDto->title);

            $this->logger->info('Found similar articles', [
                'articleId' => $articleDto->articleId->toRfc4122(),
                'count' => count($similarArticles),
            ]);

            // Score similar articles based on content similarity
            $scoredArticles = $this->scoreSimilarArticles($articleDto, $similarArticles);

            $this->logger->info('Scored similar articles', [
                'articleId' => $articleDto->articleId->toRfc4122(),
                'averageScore' => $this->calculateAverageScore($scoredArticles),
            ]);

            // Store similar articles in the database
            $this->storeSimilarArticles($articleDto->articleId, $scoredArticles);

            $this->logger->info('Stored similar articles in database', [
                'articleId' => $articleDto->articleId->toRfc4122(),
                'count' => count($scoredArticles),
            ]);

            return $scoredArticles;
        } catch (\Exception $e) {
            $this->logger->error('Failed to verify article', [
                'articleId' => $articleDto->articleId->toRfc4122(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Score similar articles based on content similarity
     *
     * @param ArticleDto $articleDto
     * @param SimilarArticle[] $similarArticles
     * @return SimilarArticleWithScore[]
     */
    private function scoreSimilarArticles(ArticleDto $articleDto, array $similarArticles): array
    {
        $scoredArticles = [];
        $originalContent = $articleDto->content ?? $articleDto->title;

        foreach ($similarArticles as $similarArticle) {
            // Compare with the description of the similar article
            // If description is empty, fall back to title
            $candidateContent = !empty($similarArticle->description) 
                ? $similarArticle->description 
                : $similarArticle->title;

            $score = $this->similarityAnalyzer->compare($originalContent, $candidateContent);

            $scoredArticles[] = SimilarArticleWithScore::fromSimilarArticle($similarArticle, $score);
        }

        // Sort by similarity score (highest first)
        usort($scoredArticles, fn($a, $b) => $b->similarityScore <=> $a->similarityScore);

        return $scoredArticles;
    }

    /**
     * Calculate average similarity score
     *
     * @param SimilarArticleWithScore[] $articles
     */
    private function calculateAverageScore(array $articles): float
    {
        if (empty($articles)) {
            return 0.0;
        }

        $totalScore = array_reduce(
            $articles,
            fn($carry, $article) => $carry + $article->similarityScore,
            0.0
        );

        return $totalScore / count($articles);
    }

    /**
     * Store similar articles in the database
     *
     * @param \Symfony\Component\Uid\Uuid $articleId
     * @param SimilarArticleWithScore[] $scoredArticles
     */
    private function storeSimilarArticles(\Symfony\Component\Uid\Uuid $articleId, array $scoredArticles): void
    {
        $article = $this->articleRepository->find($articleId);

        if ($article === null) {
            $this->logger->warning('Article not found when storing similar articles', [
                'articleId' => $articleId->toRfc4122(),
            ]);
            return;
        }

        // Clear existing similar articles and replace with new ones
        foreach ($article->getSimilarArticles() as $existingSimilar) {
            $article->removeSimilarArticle($existingSimilar);
        }
        
        $this->articleRepository->save($article, true);

        // Create and save new similar article entities
        foreach ($scoredArticles as $scoredArticle) {
            $similarArticleEntity = new \App\Entity\SimilarArticle();
            $similarArticleEntity->setArticle($article);
            $similarArticleEntity->setSource($scoredArticle->source);
            $similarArticleEntity->setAuthor($scoredArticle->author);
            $similarArticleEntity->setTitle($scoredArticle->title);
            $similarArticleEntity->setContent($scoredArticle->description);
            $similarArticleEntity->setUrl($scoredArticle->url);
            $similarArticleEntity->setScore($scoredArticle->similarityScore);
            $similarArticleEntity->setPublishedAt($scoredArticle->publishedAt);

            $this->similarArticleRepository->save($similarArticleEntity, flush: false);
        }

        // Flush all similar articles at once
        if (!empty($scoredArticles)) {
            $this->similarArticleRepository->flush();
        }
    }
}
