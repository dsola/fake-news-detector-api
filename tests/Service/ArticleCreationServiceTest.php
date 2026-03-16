<?php

namespace App\Tests\Service;

use App\Entity\Article;
use App\Event\ArticleWasCreated;
use App\Exception\CorruptedArticleContentException;
use App\Repository\ArticleRepository;
use App\Service\ArticleContentExtractor;
use App\Service\ArticleCreationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ArticleCreationServiceTest extends KernelTestCase
{
    private ArticleRepository $articleRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        
        $container = static::getContainer();
        $this->articleRepository = $container->get(ArticleRepository::class);
        
        $entityManager = $container->get('doctrine')->getManager();
        $entityManager->createQuery('DELETE FROM App\Entity\Article')->execute();
    }

    private function makeService(ArticleContentExtractor $contentExtractor, EventDispatcherInterface $eventDispatcher): ArticleCreationService
    {
        return new ArticleCreationService(
            $contentExtractor,
            $this->articleRepository,
            $eventDispatcher,
            $this->createStub(LoggerInterface::class),
        );
    }

    public function testArticleHasBeenStoredSuccessfully(): void
    {
        $contentExtractor = $this->createMock(ArticleContentExtractor::class);
        $contentExtractor->method('extractTitleFromUrl')
            ->willReturn('Extracted Article Title');
        $contentExtractor->method('extractFromUrl')
            ->willReturn('This is the extracted content from the article.');

        $service = $this->makeService($contentExtractor, $this->createMock(EventDispatcherInterface::class));

        $articleResource = $service->create(['url' => 'https://example.com/article']);

        $this->assertNotNull($articleResource->id);
        $this->assertEquals('Extracted Article Title', $articleResource->title);
        $this->assertEquals('https://example.com/article', $articleResource->url);
        $this->assertEquals('STARTED', $articleResource->status);

        $savedArticle = $this->articleRepository->find($articleResource->id);
        $this->assertNotNull($savedArticle);
        $this->assertEquals('This is the extracted content from the article.', $savedArticle->getContent());
    }

    public function testEventWasEmitted(): void
    {
        $contentExtractor = $this->createMock(ArticleContentExtractor::class);
        $contentExtractor->method('extractTitleFromUrl')
            ->willReturn('Another Test Article');
        $contentExtractor->method('extractFromUrl')
            ->willReturn('Article content here.');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof ArticleWasCreated
                    && $event->getArticleId() !== null;
            }));

        $this->makeService($contentExtractor, $eventDispatcher)
            ->create(['url' => 'https://example.com/another-article']);
    }

    public function testErrorOccurredFromArticleContentExtractor(): void
    {
        $contentExtractor = $this->createMock(ArticleContentExtractor::class);
        $contentExtractor->method('extractTitleFromUrl')
            ->willReturn('Failed Article');
        $contentExtractor->method('extractFromUrl')
            ->willThrowException(new \RuntimeException('Failed to download content'));

        $service = $this->makeService($contentExtractor, $this->createMock(EventDispatcherInterface::class));

        $this->expectException(CorruptedArticleContentException::class);
        $this->expectExceptionMessage('Failed to fetch content from URL');

        $service->create(['url' => 'https://example.com/failed']);
    }

    public function testEmptyContentThrowsCorruptedException(): void
    {
        $contentExtractor = $this->createMock(ArticleContentExtractor::class);
        $contentExtractor->method('extractTitleFromUrl')
            ->willReturn('Empty Content Article');
        $contentExtractor->method('extractFromUrl')
            ->willReturn('   ');

        $service = $this->makeService($contentExtractor, $this->createMock(EventDispatcherInterface::class));

        $this->expectException(CorruptedArticleContentException::class);
        $this->expectExceptionMessage('The downloaded content appears to be empty or corrupted');

        $service->create(['url' => 'https://example.com/empty']);
    }

    public function testMissingTitleThrowsCorruptedException(): void
    {
        $contentExtractor = $this->createMock(ArticleContentExtractor::class);
        $contentExtractor->method('extractTitleFromUrl')
            ->willReturn(null);

        $service = $this->makeService($contentExtractor, $this->createMock(EventDispatcherInterface::class));

        $this->expectException(CorruptedArticleContentException::class);
        $this->expectExceptionMessage('No title could be found in the page');

        $service->create(['url' => 'https://example.com/no-title']);
    }
}
