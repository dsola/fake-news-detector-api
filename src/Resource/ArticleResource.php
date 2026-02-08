<?php

namespace App\Resource;

use App\Entity\Article;
use Symfony\Component\Uid\Uuid;

class ArticleResource
{
    public function __construct(
        public readonly Uuid $id,
        public readonly string $title,
        public readonly string $url,
        public readonly string $status,
    ) {}

    /**
     * Create an ArticleResource from an Article entity
     */
    public static function fromEntity(Article $article): self
    {
        $status = self::computeStatus($article);

        return new self(
            id: $article->getId(),
            title: $article->getTitle(),
            url: $article->getUrl(),
            status: $status,
        );
    }

    /**
     * Compute the status based on verified_at and errored_at columns
     */
    private static function computeStatus(Article $article): string
    {
        if ($article->getErroredAt() !== null) {
            return 'ERRORED';
        }

        if ($article->getVerifiedAt() !== null) {
            return 'VERIFIED';
        }

        return 'STARTED';
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'title' => $this->title,
            'url' => $this->url,
            'status' => $this->status,
        ];
    }
}
