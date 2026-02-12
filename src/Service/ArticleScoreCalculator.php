<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\SimilarArticleWithScore;
use App\Entity\VerificationResult;

final class ArticleScoreCalculator
{
    public function __construct(
        private readonly int $limit = 10,
        private readonly float $approvalThreshold = 0.5,
    ) {}

    /**
     * @param SimilarArticleWithScore[] $articles
     */
    public function calculate(array $articles): ArticleScoreResult
    {
        $totalArticles = count($articles);

        if ($totalArticles === 0) {
            return new ArticleScoreResult(
                averageScore: 0.0,
                result: VerificationResult::REJECTED,
                totalArticles: 0,
                consideredArticles: 0,
            );
        }

        usort(
            $articles,
            static fn (SimilarArticleWithScore $articleA, SimilarArticleWithScore $articleB): int =>
                $articleB->similarityScore <=> $articleA->similarityScore
        );

        $consideredArticles = array_slice($articles, 0, $this->limit);
        $sum = array_reduce(
            $consideredArticles,
            static fn (float $carry, SimilarArticleWithScore $article): float => $carry + $article->similarityScore,
            0.0
        );

        $averageScore = $sum / count($consideredArticles);
        $result = $averageScore > $this->approvalThreshold
            ? VerificationResult::APPROVED
            : VerificationResult::REJECTED;

        return new ArticleScoreResult(
            averageScore: $averageScore,
            result: $result,
            totalArticles: $totalArticles,
            consideredArticles: count($consideredArticles),
        );
    }
}
