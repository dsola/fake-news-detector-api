<?php

namespace App\Tests\Service\Provider;

use App\Exception\SearchProviderException;
use App\Service\Provider\NewsAPIProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class NewsAPIProviderTest extends TestCase
{
    public function testSearchReturnsArticlesOnSuccessfulResponse(): void
    {
        $mockApiResponse = [
            'status' => 'ok',
            'totalResults' => 2,
            'articles' => [
                [
                    'source' => ['id' => 'bbc-news', 'name' => 'BBC News'],
                    'author' => 'John Doe',
                    'title' => 'Bitcoin reaches new high',
                    'description' => 'Bitcoin has reached a new all-time high today',
                    'url' => 'https://example.com/article1',
                    'publishedAt' => '2024-01-15T10:30:00Z',
                ],
                [
                    'source' => ['id' => 'cnn', 'name' => 'CNN'],
                    'author' => 'Jane Smith',
                    'title' => 'Bitcoin market analysis',
                    'description' => 'An in-depth analysis of the Bitcoin market',
                    'url' => 'https://example.com/article2',
                    'publishedAt' => '2024-01-14T15:45:00Z',
                ],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($mockApiResponse), ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $provider = new NewsAPIProvider($httpClient, 'test_api_key');

        $result = $provider->search('Bitcoin');

        $this->assertCount(2, $result);
        $this->assertEquals('BBC News', $result[0]->source);
        $this->assertEquals('John Doe', $result[0]->author);
        $this->assertEquals('Bitcoin reaches new high', $result[0]->title);
    }

    public function testSearchReturnsEmptyArrayOnEmptyResponse(): void
    {
        $mockApiResponse = [
            'status' => 'ok',
            'totalResults' => 0,
            'articles' => [],
        ];

        $mockResponse = new MockResponse(json_encode($mockApiResponse), ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $provider = new NewsAPIProvider($httpClient, 'test_api_key');

        $result = $provider->search('NonExistentTopic');

        $this->assertCount(0, $result);
        $this->assertIsArray($result);
    }

    public function testSearchThrowsSearchProviderExceptionOnNonOkStatus(): void
    {
        $mockApiResponse = [
            'status' => 'error',
            'code' => 'apiKeyInvalid',
            'message' => 'Your API key is invalid or incorrect.',
        ];

        $mockResponse = new MockResponse(json_encode($mockApiResponse), ['http_code' => 401]);
        $httpClient = new MockHttpClient($mockResponse);
        $provider = new NewsAPIProvider($httpClient, 'invalid_api_key');

        $this->expectException(SearchProviderException::class);
        $this->expectExceptionMessage('News API error: Your API key is invalid or incorrect.');

        $provider->search('Bitcoin');
    }

    public function testSearchThrowsSearchProviderExceptionOnConnectionTimeout(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new class('Connection timeout') extends \Exception implements TransportExceptionInterface {};
        });
        $provider = new NewsAPIProvider($httpClient, 'test_api_key');

        $this->expectException(SearchProviderException::class);
        $this->expectExceptionMessage('Failed to connect to News API: Connection timeout');

        $provider->search('Bitcoin');
    }

    public function testSearchThrowsSearchProviderExceptionOnInvalidJson(): void
    {
        $mockResponse = new MockResponse('This is not valid JSON', ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $provider = new NewsAPIProvider($httpClient, 'test_api_key');

        $this->expectException(SearchProviderException::class);
        $this->expectExceptionMessage('Malformed response from News API: invalid JSON structure');

        $provider->search('Bitcoin');
    }

    public function testSearchThrowsSearchProviderExceptionWhenMissingArticlesField(): void
    {
        $mockApiResponse = [
            'status' => 'ok',
            'totalResults' => 0,
        ];

        $mockResponse = new MockResponse(json_encode($mockApiResponse), ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $provider = new NewsAPIProvider($httpClient, 'test_api_key');

        $this->expectException(SearchProviderException::class);
        $this->expectExceptionMessage('Malformed response from News API: missing or invalid articles field');

        $provider->search('Bitcoin');
    }

    public function testSearchThrowsSearchProviderExceptionWhenArticleDataIsInvalid(): void
    {
        $mockApiResponse = [
            'status' => 'ok',
            'totalResults' => 1,
            'articles' => [
                [
                    'source' => ['name' => 'Test Source'],
                    'title' => 'Test Title',
                    'publishedAt' => 'invalid-date-format',
                ],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($mockApiResponse), ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $provider = new NewsAPIProvider($httpClient, 'test_api_key');

        $this->expectException(SearchProviderException::class);
        $this->expectExceptionMessage('Malformed response from News API: invalid article data');

        $provider->search('Bitcoin');
    }

    public function testSearchHandlesMissingOptionalFieldsGracefully(): void
    {
        $mockApiResponse = [
            'status' => 'ok',
            'totalResults' => 1,
            'articles' => [
                [
                    'source' => ['name' => 'Test Source'],
                    'title' => 'Test Title',
                    'url' => 'https://example.com',
                    'publishedAt' => '2024-01-15T10:30:00Z',
                ],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($mockApiResponse), ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $provider = new NewsAPIProvider($httpClient, 'test_api_key');

        $result = $provider->search('Test');

        $this->assertCount(1, $result);
        $this->assertEquals('Test Source', $result[0]->source);
        $this->assertEquals('', $result[0]->author);
        $this->assertEquals('', $result[0]->description);
    }

    public function testSearchThrowsSearchProviderExceptionOnNon200StatusCode(): void
    {
        $mockApiResponse = [
            'status' => 'ok',
            'articles' => [],
        ];

        $mockResponse = new MockResponse(json_encode($mockApiResponse), ['http_code' => 500]);
        $httpClient = new MockHttpClient($mockResponse);
        $provider = new NewsAPIProvider($httpClient, 'test_api_key');

        $this->expectException(SearchProviderException::class);
        $this->expectExceptionMessage('News API error:');

        $provider->search('Test');
    }

    public function testSearchThrowsSearchProviderExceptionOnErrorStatus(): void
    {
        $mockApiResponse = [
            'status' => 'error',
            'message' => 'Request rate limit exceeded',
        ];

        $mockResponse = new MockResponse(json_encode($mockApiResponse), ['http_code' => 429]);
        $httpClient = new MockHttpClient($mockResponse);
        $provider = new NewsAPIProvider($httpClient, 'test_api_key');

        $this->expectException(SearchProviderException::class);
        $this->expectExceptionMessage('News API error: Request rate limit exceeded');

        $provider->search('Test');
    }
}
