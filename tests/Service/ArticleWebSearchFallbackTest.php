<?php

namespace App\Tests\Service;

use App\Dto\SimilarArticle;
use App\Exception\SearchProviderException;
use App\Service\ArticleWebSearch;
use App\Service\Provider\ArticleSearchProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ArticleWebSearchFallbackTest extends TestCase
{
    public function testSearchReturnsResultsFromPrimaryProviderWhenAvailable(): void
    {
        // Create articles for primary provider
        $articles = [
            new SimilarArticle(
                source: 'BBC News',
                author: 'John Doe',
                title: 'Bitcoin news',
                description: 'Bitcoin article',
                url: 'https://example.com/1',
                publishedAt: new \DateTimeImmutable()
            ),
        ];

        $primaryProvider = $this->createMock(ArticleSearchProvider::class);
        $primaryProvider->expects($this->once())
            ->method('search')
            ->with('Bitcoin')
            ->willReturn($articles);

        $service = new ArticleWebSearch($primaryProvider);
        $result = $service->searchSimilarArticles('Bitcoin');

        $this->assertCount(1, $result);
        $this->assertEquals('BBC News', $result[0]->source);
    }

    public function testSearchFallsBackTSecondaryProviderWhenPrimaryFails(): void
    {
        // Create articles for fallback provider
        $fallbackArticles = [
            new SimilarArticle(
                source: 'CNN',
                author: 'Jane Smith',
                title: 'Bitcoin analysis',
                description: 'Analysis article',
                url: 'https://example.com/2',
                publishedAt: new \DateTimeImmutable()
            ),
        ];

        $primaryProvider = $this->createMock(ArticleSearchProvider::class);
        $primaryProvider->expects($this->once())
            ->method('search')
            ->willThrowException(new SearchProviderException('Primary provider error'));

        $fallbackProvider = $this->createMock(ArticleSearchProvider::class);
        $fallbackProvider->expects($this->once())
            ->method('search')
            ->with('Bitcoin')
            ->willReturn($fallbackArticles);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'Primary search provider failed, attempting fallback provider',
                $this->callback(function ($context) {
                    return $context['title'] === 'Bitcoin';
                })
            );

        $service = new ArticleWebSearch($primaryProvider, $fallbackProvider, $logger);
        $result = $service->searchSimilarArticles('Bitcoin');

        $this->assertCount(1, $result);
        $this->assertEquals('CNN', $result[0]->source);
    }

    public function testSearchLogsWhenFallbackIsUsedDueToEmptyResults(): void
    {
        $primaryProvider = $this->createMock(ArticleSearchProvider::class);
        $primaryProvider->expects($this->once())
            ->method('search')
            ->willReturn([]); // Empty results

        $fallbackArticles = [
            new SimilarArticle(
                source: 'Reuters',
                author: 'Test Author',
                title: 'Test Article',
                description: 'Test Description',
                url: 'https://example.com/3',
                publishedAt: new \DateTimeImmutable()
            ),
        ];

        $fallbackProvider = $this->createMock(ArticleSearchProvider::class);
        $fallbackProvider->expects($this->once())
            ->method('search')
            ->willReturn($fallbackArticles);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Primary search provider returned no results, attempting fallback provider',
                $this->callback(function ($context) {
                    return $context['title'] === 'Bitcoin';
                })
            );

        $service = new ArticleWebSearch($primaryProvider, $fallbackProvider, $logger);
        $result = $service->searchSimilarArticles('Bitcoin');

        $this->assertCount(1, $result);
        $this->assertEquals('Reuters', $result[0]->source);
    }

    public function testSearchThrowsExceptionWhenBothProvidersFail(): void
    {
        $primaryProvider = $this->createMock(ArticleSearchProvider::class);
        $primaryProvider->expects($this->once())
            ->method('search')
            ->willThrowException(new SearchProviderException('Primary error'));

        $fallbackProvider = $this->createMock(ArticleSearchProvider::class);
        $fallbackProvider->expects($this->once())
            ->method('search')
            ->willThrowException(new SearchProviderException('Fallback error'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Both search providers failed',
                $this->callback(function ($context) {
                    return $context['title'] === 'Bitcoin' && 
                           $context['primary_error'] === 'Primary error' &&
                           $context['fallback_error'] === 'Fallback error';
                })
            );

        $service = new ArticleWebSearch($primaryProvider, $fallbackProvider, $logger);
        
        $this->expectException(SearchProviderException::class);
        $this->expectExceptionMessage('Primary error');
        
        $service->searchSimilarArticles('Bitcoin');
    }

    public function testSearchThrowsExceptionWhenPrimaryFailsAndNoFallbackProvider(): void
    {
        $primaryProvider = $this->createMock(ArticleSearchProvider::class);
        $primaryProvider->expects($this->once())
            ->method('search')
            ->willThrowException(new SearchProviderException('Primary error'));

        $service = new ArticleWebSearch($primaryProvider);
        
        $this->expectException(SearchProviderException::class);
        $this->expectExceptionMessage('Primary error');
        
        $service->searchSimilarArticles('Bitcoin');
    }

    public function testSearchReturnsEmptyArrayWhenPrimaryReturnsEmptyAndNoFallback(): void
    {
        $primaryProvider = $this->createMock(ArticleSearchProvider::class);
        $primaryProvider->expects($this->once())
            ->method('search')
            ->willReturn([]);

        $service = new ArticleWebSearch($primaryProvider);
        $result = $service->searchSimilarArticles('Bitcoin');

        $this->assertEmpty($result);
    }

    public function testSearchDoesNotCallFallbackWhenPrimaryReturnsNonEmptyResults(): void
    {
        $articles = [
            new SimilarArticle(
                source: 'BBC News',
                author: 'John Doe',
                title: 'Bitcoin news',
                description: 'Bitcoin article',
                url: 'https://example.com/1',
                publishedAt: new \DateTimeImmutable()
            ),
        ];

        $primaryProvider = $this->createMock(ArticleSearchProvider::class);
        $primaryProvider->expects($this->once())
            ->method('search')
            ->willReturn($articles);

        $fallbackProvider = $this->createMock(ArticleSearchProvider::class);
        $fallbackProvider->expects($this->never()) // Should not be called
            ->method('search');

        $service = new ArticleWebSearch($primaryProvider, $fallbackProvider);
        $result = $service->searchSimilarArticles('Bitcoin');

        $this->assertCount(1, $result);
    }

    public function testSearchDoesNotLogWhenPrimarySucceeds(): void
    {
        $articles = [
            new SimilarArticle(
                source: 'BBC News',
                author: 'John Doe',
                title: 'Bitcoin news',
                description: 'Bitcoin article',
                url: 'https://example.com/1',
                publishedAt: new \DateTimeImmutable()
            ),
        ];

        $primaryProvider = $this->createMock(ArticleSearchProvider::class);
        $primaryProvider->expects($this->once())
            ->method('search')
            ->willReturn($articles);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())
            ->method('info');
        $logger->expects($this->never())
            ->method('warning');
        $logger->expects($this->never())
            ->method('error');

        $service = new ArticleWebSearch($primaryProvider, null, $logger);
        $result = $service->searchSimilarArticles('Bitcoin');

        $this->assertCount(1, $result);
    }

    public function testSearchWithoutLoggerDoesNotThrowError(): void
    {
        $primaryProvider = $this->createMock(ArticleSearchProvider::class);
        $primaryProvider->expects($this->once())
            ->method('search')
            ->willThrowException(new SearchProviderException('Primary error'));

        $fallbackProvider = $this->createMock(ArticleSearchProvider::class);
        $fallbackProvider->expects($this->once())
            ->method('search')
            ->willReturn([
                new SimilarArticle(
                    source: 'CNN',
                    author: 'Jane Smith',
                    title: 'Bitcoin analysis',
                    description: 'Analysis article',
                    url: 'https://example.com/2',
                    publishedAt: new \DateTimeImmutable()
                ),
            ]);

        // No logger provided (null)
        $service = new ArticleWebSearch($primaryProvider, $fallbackProvider, null);
        $result = $service->searchSimilarArticles('Bitcoin');

        $this->assertCount(1, $result);
    }
}
