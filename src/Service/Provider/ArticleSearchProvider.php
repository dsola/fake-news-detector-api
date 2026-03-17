<?php

namespace App\Service\Provider;

use App\Dto\SimilarArticle;

/**
 * Interface for article search providers using strategy pattern
 */
interface ArticleSearchProvider
{
    /**
     * Search for articles similar to the given title
     *
     * @param string $title The article title to search for
     * @return SimilarArticle[] Array of similar articles found
     * @throws \App\Exception\SearchProviderException If the search fails
     */
    public function search(string $title): array;
}
