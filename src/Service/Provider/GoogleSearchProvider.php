<?php

namespace App\Service\Provider;

use App\Dto\SimilarArticle;
use App\Exception\SearchProviderException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Google Search provider for searching articles via SerpAPI
 * @see https://serpapi.com/docs/google-news-results
 */
class GoogleSearchProvider implements ArticleSearchProvider
{
    private const SERPAPI_BASE_URL = 'https://serpapi.com/search.json';
    private const PAGE_SIZE = 50;
    private const TIMEOUT = 10;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $serpApiKey,
    ) {}

    /**
     * Search for articles with similar titles using Google Search via SerpAPI
     *
     * @param string $title The article title to search for
     * @return SimilarArticle[]
     * @throws SearchProviderException
     */
    public function search(string $title): array
    {
        try {
            $response = $this->httpClient->request('GET', self::SERPAPI_BASE_URL, [
                'query' => [
                    'api_key' => $this->serpApiKey,
                    'q' => urlencode($title),
                    'tbm' => 'nws', // news search
                    'num' => self::PAGE_SIZE,
                ],
                'timeout' => self::TIMEOUT,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);

            if ($statusCode !== 200) {
                throw new SearchProviderException(
                    sprintf('Google Search API returned status code %d', $statusCode)
                );
            }

            $results = json_decode($content, true);

            if (!is_array($results)) {
                throw new SearchProviderException('Malformed response from Google Search: response is not an array');
            }

            // Check for API errors
            if (isset($results['error'])) {
                throw new SearchProviderException(
                    sprintf('Google Search API error: %s', $results['error'])
                );
            }

            // Extract news results
            if (!isset($results['news_results']) || !is_array($results['news_results'])) {
                // No results found, return empty array
                return [];
            }

            return $this->mapArticles($results['news_results']);
        } catch (SearchProviderException $e) {
            throw $e;
        } catch (TransportExceptionInterface $e) {
            throw new SearchProviderException(
                sprintf('Failed to connect to Google Search API: %s', $e->getMessage()),
                0,
                $e
            );
        } catch (\JsonException $e) {
            throw new SearchProviderException(
                'Malformed response from Google Search: invalid JSON',
                0,
                $e
            );
        } catch (\Exception $e) {
            throw new SearchProviderException(
                sprintf('Unexpected error in Google Search provider: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Map Google Search results to SimilarArticle DTOs
     *
     * @param array $articles
     * @return SimilarArticle[]
     * @throws SearchProviderException
     */
    private function mapArticles(array $articles): array
    {
        $similarArticles = [];

        foreach ($articles as $article) {
            try {
                // Google Search returns results in a different format
                $publishedAt = 'now';
                if (isset($article['date'])) {
                    // Use the date string provided by SerpAPI
                    $publishedAt = $article['date'];
                }

                $similarArticles[] = new SimilarArticle(
                    source: $article['source'] ?? '',
                    author: $article['source'] ?? '', // Google Search doesn't provide author field
                    title: $article['title'] ?? '',
                    description: $article['snippet'] ?? '',
                    url: $article['link'] ?? '',
                    publishedAt: new \DateTimeImmutable($publishedAt),
                );
            } catch (\Exception $e) {
                throw new SearchProviderException(
                    'Malformed response from Google Search: invalid article data',
                    0,
                    $e
                );
            }
        }

        return $similarArticles;
    }
}

