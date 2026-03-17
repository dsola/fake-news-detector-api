<?php

namespace App\Dto;

class SimilarArticleWithScore extends SimilarArticle
{
    public function __construct(
        string $source,
        string $author,
        string $title,
        string $description,
        string $url,
        \DateTimeInterface $publishedAt,
        public readonly float $similarityScore,
    ) {
        parent::__construct($source, $author, $title, $description, $url, $publishedAt);
    }

    /**
     * Create from SimilarArticle with a similarity score
     */
    public static function fromSimilarArticle(SimilarArticle $article, float $score): self
    {
        return new self(
            source: $article->source,
            author: $article->author,
            title: $article->title,
            description: $article->description,
            url: $article->url,
            publishedAt: $article->publishedAt,
            similarityScore: $score,
        );
    }
}
