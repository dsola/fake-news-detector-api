<?php

namespace App\Service;

use App\Entity\Article;
use App\Event\ArticleWasCreated;
use App\Exception\CorruptedArticleContentException;
use App\Repository\ArticleRepository;
use App\Resource\ArticleResource;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ArticleCreationService
{
    public function __construct(
        private readonly ArticleContentExtractor $contentExtractor,
        private readonly ArticleRepository $articleRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Create an article from the provided data
     * 
     * @throws CorruptedArticleContentException
     */
    public function create(array $data): ArticleResource
    {
        $url = trim($data['url']);

        // Extract title from URL
        try {
            $title = $this->contentExtractor->extractTitleFromUrl($url);
        } catch (\RuntimeException $e) {
            $this->logger->error('Failed to extract title from URL', ['url' => $url, 'error' => $e->getMessage()]);
            throw new CorruptedArticleContentException(
                sprintf('Failed to fetch title from URL: %s', $e->getMessage())
            );
        }

        if ($title === null || $title === '') {
            $this->logger->error('No title could be found in the page', ['url' => $url]);
            throw new CorruptedArticleContentException(
                'No title could be found in the page'
            );
        }

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
