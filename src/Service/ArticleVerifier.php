<?php

namespace App\Service;

use App\Dto\ArticleDto;
use App\Dto\SimilarArticle;
use Psr\Log\LoggerInterface;

class ArticleVerifier
{
    public function __construct(
        private readonly ArticleWebSearch $articleWebSearch,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Verify an article by searching for similar articles
     *
     * @return SimilarArticle[]
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

            return $similarArticles;
        } catch (\Exception $e) {
            $this->logger->error('Failed to verify article', [
                'articleId' => $articleDto->articleId->toRfc4122(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
