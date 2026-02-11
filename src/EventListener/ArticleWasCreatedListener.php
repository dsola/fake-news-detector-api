<?php

namespace App\EventListener;

use App\Dto\ArticleDto;
use App\Event\ArticleWasCreated;
use App\Repository\ArticleRepository;
use App\Service\ArticleVerifier;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ArticleWasCreated::class, method: 'onArticleWasCreated')]
class ArticleWasCreatedListener
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly ArticleVerifier $articleVerifier,
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

            $articleDto = new ArticleDto(
                articleId: $articleId,
                title: $article->getTitle(),
                url: $article->getUrl(),
                content: $article->getContent(),
            );

            $this->articleVerifier->verify($articleDto);
        } catch (\Exception $e) {
            $this->logger->error('Error processing ArticleWasCreated event', [
                'articleId' => $articleId->toRfc4122(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
