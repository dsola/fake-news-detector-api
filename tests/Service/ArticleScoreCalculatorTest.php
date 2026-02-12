<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\SimilarArticleWithScore;
use App\Entity\VerificationResult;
use App\Service\ArticleScoreCalculator;
use PHPUnit\Framework\TestCase;

final class ArticleScoreCalculatorTest extends TestCase
{
    public function testEmptyArticlesAreRejected(): void
    {
        $calculator = new ArticleScoreCalculator();
        $result = $calculator->calculate([]);

        $this->assertSame(0.0, $result->averageScore);
        $this->assertSame(0, $result->totalArticles);
        $this->assertSame(0, $result->consideredArticles);
        $this->assertSame(VerificationResult::REJECTED, $result->result);
    }

    public function testBestArticlesDetermineApproval(): void
    {
        $calculator = new ArticleScoreCalculator();
        $scores = [1.0, 0.9, 0.8, 0.7, 0.6, 0.5, 0.4, 0.3, 0.2, 0.1, 0.05, 0.01];
        $articles = $this->buildArticles($scores);

        $result = $calculator->calculate($articles);

        $this->assertSame(12, $result->totalArticles);
        $this->assertSame(10, $result->consideredArticles);
        $this->assertEqualsWithDelta(0.55, $result->averageScore, 0.0001);
        $this->assertSame(VerificationResult::APPROVED, $result->result);
    }

    public function testLowAverageScoreResultsInRejection(): void
    {
        $calculator = new ArticleScoreCalculator();
        $articles = $this->buildArticles([0.4, 0.3, 0.2]);

        $result = $calculator->calculate($articles);

        $this->assertSame(3, $result->totalArticles);
        $this->assertSame(3, $result->consideredArticles);
        $this->assertLessThanOrEqual(0.5, $result->averageScore);
        $this->assertSame(VerificationResult::REJECTED, $result->result);
    }

    /**
     * @param float[] $scores
     * @return SimilarArticleWithScore[]
     */
    private function buildArticles(array $scores): array
    {
        $publishedAt = new \DateTimeImmutable('2024-01-01');
        $articles = [];

        foreach ($scores as $score) {
            $articles[] = new SimilarArticleWithScore(
                source: 'source',
                author: 'author',
                title: 'title',
                description: 'description',
                url: 'https://example.com',
                publishedAt: $publishedAt,
                similarityScore: $score,
            );
        }

        return $articles;
    }
}
