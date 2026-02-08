<?php

namespace App\EventListener;

use App\Event\ArticleWasCreated;
use App\Repository\ArticleRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ArticleWasCreated::class, method: 'onArticleWasCreated')]
class ArticleWasCreatedListener
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function onArticleWasCreated(ArticleWasCreated $event): void
    {
        $articleId = $event->getArticleId();

        try {
            // Lookup the article in the database
            $article = $this->articleRepository->find($articleId);

            if ($article === null) {
                $this->logger->warning('Article not found in database', ['articleId' => $articleId->toRfc4122()]);
                return;
            }

            $this->logger->info('Article was created and verified in database', [
                'articleId' => $articleId->toRfc4122(),
                'title' => $article->getTitle(),
                'url' => $article->getUrl(),
            ]);

            // You can add more processing here as needed
            // For example: trigger verification, send notifications, etc.
        } catch (\Exception $e) {
            $this->logger->error('Error processing ArticleWasCreated event', [
                'articleId' => $articleId->toRfc4122(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
