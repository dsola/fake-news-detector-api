<?php

namespace App\Tests\Service;

use App\Exception\SearchProviderException;
use App\Service\Provider\NewsAPIProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Legacy tests for NewsAPIProvider (previously ArticleWebSearchTest)
 * These tests verify backward compatibility and core functionality
 */
class ArticleWebSearchTest extends TestCase
{
    public function testCollectionIsMappedCorrectlyWithMockResponse(): void
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
        $this->assertEquals('Bitcoin has reached a new all-time high today', $result[0]->description);
        $this->assertEquals('https://example.com/article1', $result[0]->url);
        $this->assertEquals('2024-01-15T10:30:00Z', $result[0]->publishedAt->format('Y-m-d\TH:i:s\Z'));

        $this->assertEquals('CNN', $result[1]->source);
        $this->assertEquals('Jane Smith', $result[1]->author);
        $this->assertEquals('Bitcoin market analysis', $result[1]->title);
        $this->assertEquals('An in-depth analysis of the Bitcoin market', $result[1]->description);
        $this->assertEquals('https://example.com/article2', $result[1]->url);
        $this->assertEquals('2024-01-14T15:45:00Z', $result[1]->publishedAt->format('Y-m-d\TH:i:s\Z'));
    }

    public function testCollectionIsEmptyIfApiReturnsEmptyResponse(): void
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

    public function testErrorIsReportedCorrectlyWhenApiGivesBackAnError(): void
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

    public function testErrorIsReportedCorrectlyWhenApiTimesOut(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new class('Connection timeout') extends \Exception implements TransportExceptionInterface {};
        });
        $provider = new NewsAPIProvider($httpClient, 'test_api_key');

        $this->expectException(SearchProviderException::class);
        $this->expectExceptionMessage('Failed to connect to News API: Connection timeout');

        $provider->search('Bitcoin');
    }

    public function testErrorIsReportedCorrectlyWhenApiReturnsMalformedResponse(): void
    {
        $mockResponse = new MockResponse('This is not valid JSON', ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $provider = new NewsAPIProvider($httpClient, 'test_api_key');

        $this->expectException(SearchProviderException::class);
        $this->expectExceptionMessage('Malformed response from News API: invalid JSON structure');

        $provider->search('Bitcoin');
    }

    public function testErrorIsReportedWhenResponseMissingArticlesField(): void
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

    public function testErrorIsReportedWhenArticleDataIsInvalid(): void
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

    public function testHandlesMissingOptionalFieldsGracefully(): void
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
        $this->assertEquals('Test Title', $result[0]->title);
        $this->assertEquals('https://example.com', $result[0]->url);
    }
}
