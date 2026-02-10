<?php

namespace App\Service;

use App\Dto\SimilarArticle;
use App\Exception\NewsApiException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class ArticleWebSearch
{
    private const NEWS_API_BASE_URL = 'https://newsapi.org/v2/everything';
    private const PAGE_SIZE = 50;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $newsApiKey,
    ) {}

    /**
     * Search for articles with similar titles
     *
     * @param string $title The article title to search for
     * @return SimilarArticle[]
     * @throws NewsApiException
     */
    public function searchSimilarArticles(string $title): array
    {
        try {
            $response = $this->httpClient->request('GET', self::NEWS_API_BASE_URL, [
                'query' => [
                    'q' => $title,
                    'apiKey' => $this->newsApiKey,
                    'pageSize' => self::PAGE_SIZE,
                ],
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);

            if ($statusCode !== 200) {
                $data = json_decode($content, true);
                $errorMessage = $data['message'] ?? 'Unknown error from News API';
                throw new NewsApiException(
                    sprintf('News API error: %s', $errorMessage),
                );
            }

            $data = json_decode($content, true);

            if (!is_array($data)) {
                throw new NewsApiException('Malformed response from News API: invalid JSON structure');
            }

            if (!isset($data['status']) || $data['status'] !== 'ok') {
                $errorMessage = $data['message'] ?? 'Unknown error';
                throw new NewsApiException(sprintf('News API error: %s', $errorMessage));
            }

            if (!isset($data['articles']) || !is_array($data['articles'])) {
                throw new NewsApiException('Malformed response from News API: missing or invalid articles field');
            }

            return $this->mapArticles($data['articles']);
        } catch (TransportExceptionInterface $e) {
            throw new NewsApiException(
                sprintf('Failed to connect to News API: %s', $e->getMessage()),
                0,
                $e
            );
        } catch (\JsonException $e) {
            throw new NewsApiException(
                'Malformed response from News API: invalid JSON',
                0,
                $e
            );
        }
    }

    /**
     * @param array $articles
     * @return SimilarArticle[]
     * @throws NewsApiException
     */
    private function mapArticles(array $articles): array
    {
        $similarArticles = [];

        foreach ($articles as $article) {
            try {
                $similarArticles[] = new SimilarArticle(
                    source: $article['source']['name'] ?? '',
                    author: $article['author'] ?? '',
                    title: $article['title'] ?? '',
                    description: $article['description'] ?? '',
                    url: $article['url'] ?? '',
                    publishedAt: new \DateTimeImmutable($article['publishedAt'] ?? 'now'),
                );
            } catch (\Exception $e) {
                throw new NewsApiException(
                    'Malformed response from News API: invalid article data',
                    0,
                    $e
                );
            }
        }

        return $similarArticles;
    }
}
