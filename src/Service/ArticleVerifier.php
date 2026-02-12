<?php

namespace App\Service;

use App\Dto\ArticleDto;
use App\Dto\SimilarArticle;
use App\Dto\SimilarArticleWithScore;
use App\Entity\Article;
use App\Entity\SimilarArticle as SimilarArticleEntity;
use App\Entity\Verification;
use App\Entity\VerificationResult;
use App\Repository\ArticleRepository;
use App\Repository\SimilarArticleRepository;
use App\Service\ArticleScoreCalculator;
use App\Service\RelevantWordsExtractor;
use Psr\Log\LoggerInterface;

class ArticleVerifier
{
    public function __construct(
        private readonly ArticleWebSearch $articleWebSearch,
        private readonly SimilarityAnalyzer $similarityAnalyzer,
        private readonly ArticleRepository $articleRepository,
        private readonly SimilarArticleRepository $similarArticleRepository,
        private readonly LoggerInterface $logger,
        private readonly RelevantWordsExtractor $relevantWordsExtractor,
        private readonly ArticleScoreCalculator $scoreCalculator,
    ) {}

    /**
     * Verify an article by searching for similar articles and scoring them
     *
     * @return SimilarArticleWithScore[]
     */
    public function verify(Article $article, ArticleDto $articleDto): array
    {
        $verification = $this->createPendingVerification($article);
        $scoredArticles = [];

        try {
            $this->logger->info('Verifying article', [
                'articleId' => $articleDto->articleId->toRfc4122(),
                'title' => $articleDto->title,
            ]);

            $searchTitle = $this->buildSearchQuery($articleDto->title);
            $similarArticles = $this->articleWebSearch->searchSimilarArticles($searchTitle);

            $this->logger->info('Found similar articles', [
                'articleId' => $articleDto->articleId->toRfc4122(),
                'count' => count($similarArticles),
            ]);

            $scoredArticles = $this->scoreSimilarArticles($articleDto, $similarArticles);
            $scoreResult = $this->scoreCalculator->calculate($scoredArticles);

            $this->logger->info('Scored similar articles', [
                'articleId' => $articleDto->articleId->toRfc4122(),
                'averageScore' => $scoreResult->averageScore,
                'result' => $scoreResult->result->value,
            ]);

            $verification->setResult($scoreResult->result->value);
            $verification->setMetadata([
                'originalTitle' => $articleDto->title,
                'searchTitle' => $searchTitle,
                'averageScore' => $scoreResult->averageScore,
                'consideredArticles' => $scoreResult->consideredArticles,
                'totalArticles' => $scoreResult->totalArticles,
            ]);

            $article->setVerifiedAt(new \DateTimeImmutable());
            $article->setErroredAt(null);

            $this->storeSimilarArticles($article, $scoredArticles);

            $this->logger->info('Stored similar articles in database', [
                'articleId' => $articleDto->articleId->toRfc4122(),
                'count' => count($scoredArticles),
            ]);

            return $scoredArticles;
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to verify article', [
                'articleId' => $articleDto->articleId->toRfc4122(),
                'error' => $exception->getMessage(),
            ]);

            $verification->setResult(VerificationResult::REJECTED->value);
            $verification->setErroredAt(new \DateTimeImmutable());
            $article->setErroredAt(new \DateTimeImmutable());
            $article->setVerifiedAt(null);

            throw $exception;
        } finally {
            $verification->setTerminatedAt(new \DateTimeImmutable());
            $this->articleRepository->save($article);
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
     * Store similar articles in the database
     *
     * @param Article $article
     * @param SimilarArticleWithScore[] $scoredArticles
     */
    private function storeSimilarArticles(Article $article, array $scoredArticles): void
    {
        $hasChanges = false;

        foreach ($article->getSimilarArticles() as $existingSimilar) {
            $article->removeSimilarArticle($existingSimilar);
            $this->similarArticleRepository->remove($existingSimilar);
            $hasChanges = true;
        }

        foreach ($scoredArticles as $scoredArticle) {
            $hasChanges = true;
            $similarArticleEntity = new SimilarArticleEntity();
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

        if ($hasChanges) {
            $this->similarArticleRepository->flush();
        }
    }

    private function buildSearchQuery(string $title): string
    {
        $tokens = $this->relevantWordsExtractor->extract($title, maxWords: 0);

        if ($tokens === []) {
            return $title;
        }

        return implode(' ', $tokens);
    }

    private function createPendingVerification(Article $article): Verification
    {
        $verification = new Verification();
        $verification->setType('SIMILAR_CONTENT');
        $verification->setResult(VerificationResult::PENDING->value);
        $verification->setMetadata([]);
        $verification->setStartedAt(new \DateTimeImmutable());
        $article->addVerification($verification);

        return $verification;
    }
}
