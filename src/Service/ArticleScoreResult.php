<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\VerificationResult;

final class ArticleScoreResult
{
    public function __construct(
        public readonly float $averageScore,
        public readonly VerificationResult $result,
        public readonly int $totalArticles,
        public readonly int $consideredArticles,
    ) {}
}
