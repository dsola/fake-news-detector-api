<?php

namespace App\Dto;

use Symfony\Component\Uid\Uuid;

class ArticleDto
{
    public function __construct(
        public readonly Uuid $articleId,
        public readonly string $title,
        public readonly string $url,
    ) {}
}
