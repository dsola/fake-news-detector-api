<?php

namespace App\Event;

use Symfony\Component\Uid\Uuid;

class ArticleWasCreated
{
    public function __construct(private readonly Uuid $articleId) {}

    public function getArticleId(): Uuid
    {
        return $this->articleId;
    }
}
