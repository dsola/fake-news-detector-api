<?php

namespace App\Tests\Service\Provider;

use App\Exception\SearchProviderException;
use App\Service\Provider\GoogleSearchProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GoogleSearchProviderTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private GoogleSearchProvider $provider;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->provider = new GoogleSearchProvider($this->httpClient, 'test_api_key');
    }

    public function testSearchReturnsArticlesOnSuccessfulResponse(): void
    {
        $responseData = [
            'news_results' => [
                [
                    'title' => 'Bitcoin reaches new high',
                    'link' => 'https://example.com/article1',
                    'source' => 'BBC News',
                    'snippet' => 'Bitcoin has reached a new all-time high today',
                    'date' => '2024-01-15T10:30:00Z',
                ],
                [
                    'title' => 'Bitcoin market analysis',
                    'link' => 'https://example.com/article2',
                    'source' => 'CNN',
                    'snippet' => 'An in-depth analysis of the Bitcoin market',
                    'date' => '2024-01-14T15:45:00Z',
                ],
            ],
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn(json_encode($responseData));

        $this->httpClient->method('request')->willReturn($response);

        $articles = $this->provider->search('Bitcoin');

        $this->assertCount(2, $articles);
        $this->assertEquals('Bitcoin reaches new high', $articles[0]->title);
        $this->assertEquals('https://example.com/article1', $articles[0]->url);
        $this->assertEquals('BBC News', $articles[0]->source);
    }

    public function testSearchReturnsEmptyArrayWhenNoNewsResults(): void
    {
        $responseData = [
            'search_results' => [],
            // No 'news_results' key
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn(json_encode($responseData));

        $this->httpClient->method('request')->willReturn($response);

        $articles = $this->provider->search('nonexistent query');

        $this->assertEmpty($articles);
    }

    public function testSearchThrowsSearchProviderExceptionOnApiError(): void
    {
        $responseData = [
            'error' => 'Invalid API key',
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn(json_encode($responseData));

        $this->httpClient->method('request')->willReturn($response);

        $this->expectException(SearchProviderException::class);
        $this->expectExceptionMessage('Invalid API key');

        $this->provider->search('Bitcoin');
    }

    public function testSearchThrowsSearchProviderExceptionOnMalformedResponse(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn('This is not valid JSON');

        $this->httpClient->method('request')->willReturn($response);

        $this->expectException(SearchProviderException::class);
        $this->expectExceptionMessage('response is not an array');

        $this->provider->search('Bitcoin');
    }

    public function testSearchHandlesArticleWithMissingOptionalFields(): void
    {
        $responseData = [
            'news_results' => [
                [
                    'title' => 'Test Article',
                    'link' => 'https://example.com/article',
                    'source' => 'Test Source',
                    // Missing snippet and date
                ],
            ],
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn(json_encode($responseData));

        $this->httpClient->method('request')->willReturn($response);

        $articles = $this->provider->search('Test');

        $this->assertCount(1, $articles);
        $this->assertEquals('Test Article', $articles[0]->title);
        $this->assertEquals('', $articles[0]->description);
    }

    public function testSearchExtractsCorrectFieldsFromGoogleSearchResults(): void
    {
        $responseData = [
            'news_results' => [
                [
                    'title' => 'Article Title',
                    'link' => 'https://example.com/article',
                    'source' => 'News Source',
                    'snippet' => 'Article description',
                    'date' => '2024-01-15T10:30:00Z',
                ],
            ],
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn(json_encode($responseData));

        $this->httpClient->method('request')->willReturn($response);

        $articles = $this->provider->search('Article');

        $this->assertCount(1, $articles);
        $this->assertEquals('Article Title', $articles[0]->title);
        $this->assertEquals('https://example.com/article', $articles[0]->url);
        $this->assertEquals('News Source', $articles[0]->source);
        $this->assertEquals('Article description', $articles[0]->description);
    }
}
