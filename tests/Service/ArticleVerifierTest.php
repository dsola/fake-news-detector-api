<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\ArticleDto;
use App\Dto\SimilarArticle;
use App\Entity\Article;
use App\Entity\VerificationResult;
use App\Exception\NewsApiException;
use App\Repository\ArticleRepository;
use App\Repository\SimilarArticleRepository;
use App\Service\ArticleScoreCalculator;
use App\Service\ArticleVerifier;
use App\Service\ArticleWebSearch;
use App\Service\SimilarityAnalyzer;
use App\Service\RelevantWordsExtractor;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

final class ArticleVerifierTest extends TestCase
{
    public function testVerificationApprovesWhenHighAverageScore(): void
    {
        $article = new Article();
        $article->setTitle('News');
        $article->setUrl('https://example.com');
        $article->setContent('Something happened.');

        $articleDto = new ArticleDto(
            articleId: $article->getId(),
            title: $article->getTitle(),
            url: $article->getUrl(),
            content: $article->getContent(),
        );

        $articleWebSearch = $this->createMock(ArticleWebSearch::class);
        $articleWebSearch->expects(self::once())
            ->method('searchSimilarArticles')
            ->with('news')
            ->willReturn([
                new SimilarArticle(
                    source: 'source',
                    author: 'author',
                    title: 'match',
                    description: 'description',
                    url: 'https://example.com/match',
                    publishedAt: new \DateTimeImmutable('2025-01-01'),
                ),
            ]);

        $similarityAnalyzer = $this->createMock(SimilarityAnalyzer::class);
        $similarityAnalyzer->method('compare')->willReturn(0.8);

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->expects(self::once())->method('save')->willReturnArgument(0);

        $similarArticleRepository = $this->createMock(SimilarArticleRepository::class);
        $similarArticleRepository->expects(self::once())->method('save');
        $similarArticleRepository->expects(self::once())->method('flush');
        $similarArticleRepository->method('remove');

        $logger = $this->createStub(LoggerInterface::class);

        $relevantWordsExtractor = $this->createMock(RelevantWordsExtractor::class);
        $relevantWordsExtractor->expects(self::once())
            ->method('extract')
            ->with($articleDto->title, 0, 3)
            ->willReturn(['news']);

        $scoreCalculator = new ArticleScoreCalculator();

        $verifier = new ArticleVerifier(
            $articleWebSearch,
            $similarityAnalyzer,
            $articleRepository,
            $similarArticleRepository,
            $logger,
            $relevantWordsExtractor,
            $scoreCalculator,
        );

        $result = $verifier->verify($article, $articleDto);

        $this->assertCount(1, $result);

        $verification = $article->getVerifications()->first();
        $this->assertSame(VerificationResult::APPROVED->value, $verification->getResult());
        $this->assertNotNull($verification->getTerminatedAt());
        $this->assertNull($verification->getErroredAt());
        $this->assertSame('news', $verification->getMetadata()['searchTitle']);
        $this->assertSame(0.8, $verification->getMetadata()['averageScore']);
        $this->assertSame(1, $verification->getMetadata()['consideredArticles']);
        $this->assertNotNull($article->getVerifiedAt());
        $this->assertNull($article->getErroredAt());
    }

    public function testVerificationRejectsWhenNoSimilarArticles(): void
    {
        $article = new Article();
        $article->setTitle('Unhandled');
        $article->setUrl('https://example.com');
        $article->setContent('No matches yet.');

        $articleDto = new ArticleDto(
            articleId: $article->getId(),
            title: $article->getTitle(),
            url: $article->getUrl(),
            content: $article->getContent(),
        );

        $articleWebSearch = $this->createMock(ArticleWebSearch::class);
        $articleWebSearch->method('searchSimilarArticles')->willReturn([]);

        $similarityAnalyzer = $this->createMock(SimilarityAnalyzer::class);
        $similarityAnalyzer->method('compare')->willReturn(0.0);

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('save')->willReturnArgument(0);

        $similarArticleRepository = $this->createMock(SimilarArticleRepository::class);
        $similarArticleRepository->expects(self::never())->method('save');
        $similarArticleRepository->expects(self::never())->method('flush');
        $similarArticleRepository->method('remove');

        $logger = $this->createStub(LoggerInterface::class);

        $relevantWordsExtractor = $this->createMock(RelevantWordsExtractor::class);
        $relevantWordsExtractor->method('extract')->willReturn(['query']);

        $scoreCalculator = new ArticleScoreCalculator();

        $verifier = new ArticleVerifier(
            $articleWebSearch,
            $similarityAnalyzer,
            $articleRepository,
            $similarArticleRepository,
            $logger,
            $relevantWordsExtractor,
            $scoreCalculator,
        );

        $verifier->verify($article, $articleDto);

        $verification = $article->getVerifications()->first();
        $this->assertSame(VerificationResult::REJECTED->value, $verification->getResult());
        $this->assertSame(0, $verification->getMetadata()['totalArticles']);
        $this->assertSame(0, $verification->getMetadata()['consideredArticles']);
        $this->assertSame(0.0, $verification->getMetadata()['averageScore']);
        $this->assertNotNull($article->getVerifiedAt());
        $this->assertNull($article->getErroredAt());
    }

    public function testVerificationRecordsErroredTimestampsWhenSearchFails(): void
    {
        $article = new Article();
        $article->setTitle('Fails');
        $article->setUrl('https://example.com');
        $article->setContent('Something failed.');

        $articleDto = new ArticleDto(
            articleId: $article->getId(),
            title: $article->getTitle(),
            url: $article->getUrl(),
            content: $article->getContent(),
        );

        $articleWebSearch = $this->createMock(ArticleWebSearch::class);
        $exception = new NewsApiException('boom');
        $articleWebSearch->method('searchSimilarArticles')->willThrowException($exception);

        $similarityAnalyzer = $this->createMock(SimilarityAnalyzer::class);

        $articleRepository = $this->createMock(ArticleRepository::class);
        $articleRepository->method('save')->willReturnArgument(0);

        $similarArticleRepository = $this->createMock(SimilarArticleRepository::class);
        $similarArticleRepository->expects(self::never())->method('save');
        $similarArticleRepository->expects(self::never())->method('flush');
        $similarArticleRepository->method('remove');

        $logger = $this->createStub(LoggerInterface::class);

        $relevantWordsExtractor = $this->createMock(RelevantWordsExtractor::class);
        $relevantWordsExtractor->method('extract')->willReturn(['failure']);

        $scoreCalculator = new ArticleScoreCalculator();

        $verifier = new ArticleVerifier(
            $articleWebSearch,
            $similarityAnalyzer,
            $articleRepository,
            $similarArticleRepository,
            $logger,
            $relevantWordsExtractor,
            $scoreCalculator,
        );

        $this->expectException(NewsApiException::class);
        try {
            $verifier->verify($article, $articleDto);
        } catch (NewsApiException $caught) {
            $this->assertNotNull($article->getErroredAt());
            $this->assertNull($article->getVerifiedAt());
            $verification = $article->getVerifications()->first();
            $this->assertNotNull($verification->getErroredAt());
            $this->assertNotNull($verification->getTerminatedAt());
            throw $caught;
        }
    }
}
