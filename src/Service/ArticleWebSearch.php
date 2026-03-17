<?php

namespace App\Service;

use App\Dto\SimilarArticle;
use App\Exception\NewsApiException;
use App\Exception\SearchProviderException;
use App\Service\Provider\ArticleSearchProvider;
use Psr\Log\LoggerInterface;

/**
 * Article Web Search service with multiple providers and fallback strategy
 */
class ArticleWebSearch
{
    public function __construct(
        private readonly ArticleSearchProvider $primaryProvider,
        private readonly ?ArticleSearchProvider $fallbackProvider = null,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Search for articles with similar titles
     * Tries primary provider first, falls back to secondary if available
     *
     * @param string $title The article title to search for
     * @return SimilarArticle[]
     * @throws SearchProviderException If both providers fail
     */
    public function searchSimilarArticles(string $title): array
    {
        try {
            $articles = $this->primaryProvider->search($title);
            
            // If primary provider returns results, use them even if it's an empty array
            if (!empty($articles)) {
                return $articles;
            }
            
            // If primary provider returns empty results, try fallback
            if ($this->fallbackProvider === null) {
                return [];
            }
            
            $this->logger?->info(
                'Primary search provider returned no results, attempting fallback provider',
                ['title' => $title]
            );
            
            return $this->fallbackProvider->search($title);
        } catch (SearchProviderException $e) {
            // Primary provider failed, try fallback if available
            if ($this->fallbackProvider === null) {
                throw $e;
            }
            
            $this->logger?->warning(
                'Primary search provider failed, attempting fallback provider',
                [
                    'title' => $title,
                    'error' => $e->getMessage(),
                ]
            );
            
            try {
                return $this->fallbackProvider->search($title);
            } catch (SearchProviderException $fallbackError) {
                $this->logger?->error(
                    'Both search providers failed',
                    [
                        'title' => $title,
                        'primary_error' => $e->getMessage(),
                        'fallback_error' => $fallbackError->getMessage(),
                    ]
                );
                
                // Throw the primary error since it tried first
                throw $e;
            }
        }
    }
}
