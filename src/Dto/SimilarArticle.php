<?php

namespace App\Dto;

class SimilarArticle
{
    public function __construct(
        public readonly string $source,
        public readonly string $author,
        public readonly string $title,
        public readonly string $description,
        public readonly string $url,
        public readonly \DateTimeInterface $publishedAt,
    ) {}
}
