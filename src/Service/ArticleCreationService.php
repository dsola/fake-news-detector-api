<?php

namespace App\Service;

use App\Entity\Article;
use App\Event\ArticleWasCreated;
use App\Exception\CorruptedArticleContentException;
use App\Repository\ArticleRepository;
use App\Resource\ArticleResource;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ArticleCreationService
{
    public function __construct(
        private readonly ArticleContentExtractor $contentExtractor,
        private readonly ArticleRepository $articleRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Create an article from the provided data
     * 
     * @throws CorruptedArticleContentException
     */
    public function create(array $data): ArticleResource
    {
        $title = trim($data['title']);
        $url = trim($data['url']);

        // Extract content from URL
        try {
            $content = $this->contentExtractor->extractFromUrl($url);
        } catch (\RuntimeException $e) {
            throw new CorruptedArticleContentException(
                sprintf('Failed to fetch content from URL: %s', $e->getMessage())
            );
        }

        // Validate that content is not empty or corrupted
        if (empty(trim($content))) {
            throw new CorruptedArticleContentException(
                'The downloaded content appears to be empty or corrupted'
            );
        }

        // Create and persist the article
        $article = new Article();
        $article->setTitle($title);
        $article->setUrl($url);
        $article->setContent($content);

        $article = $this->articleRepository->save($article);

        // Dispatch the event
        $event = new ArticleWasCreated($article->getId());
        $this->eventDispatcher->dispatch($event);

        return ArticleResource::fromEntity($article);
    }
}
