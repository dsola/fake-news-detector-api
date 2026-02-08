<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Component\Uid\Uuid;

class ArticleWasCreated extends Event
{
    public function __construct(private readonly Uuid $articleId) {}

    public function getArticleId(): Uuid
    {
        return $this->articleId;
    }
}
