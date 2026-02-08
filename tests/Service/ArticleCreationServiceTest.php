<?php

namespace App\Tests\Service;

use App\Entity\Article;
use App\Event\ArticleWasCreated;
use App\Exception\CorruptedArticleContentException;
use App\Repository\ArticleRepository;
use App\Service\ArticleContentExtractor;
use App\Service\ArticleCreationService;
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

    public function testArticleHasBeenStoredSuccessfully(): void
    {
        $contentExtractor = $this->createMock(ArticleContentExtractor::class);
        $contentExtractor->method('extractFromUrl')
            ->willReturn('This is the extracted content from the article.');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        
        $service = new ArticleCreationService(
            $contentExtractor,
            $this->articleRepository,
            $eventDispatcher
        );

        $data = [
            'title' => 'Test Article Title',
            'url' => 'https://example.com/article',
        ];

        $articleResource = $service->create($data);

        $this->assertNotNull($articleResource->id);
        $this->assertEquals('Test Article Title', $articleResource->title);
        $this->assertEquals('https://example.com/article', $articleResource->url);
        $this->assertEquals('STARTED', $articleResource->status);

        $savedArticle = $this->articleRepository->find($articleResource->id);
        $this->assertNotNull($savedArticle);
        $this->assertEquals('This is the extracted content from the article.', $savedArticle->getContent());
    }

    public function testEventWasEmitted(): void
    {
        $contentExtractor = $this->createMock(ArticleContentExtractor::class);
        $contentExtractor->method('extractFromUrl')
            ->willReturn('Article content here.');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof ArticleWasCreated 
                    && $event->getArticleId() !== null;
            }));
        
        $service = new ArticleCreationService(
            $contentExtractor,
            $this->articleRepository,
            $eventDispatcher
        );

        $data = [
            'title' => 'Another Test Article',
            'url' => 'https://example.com/another-article',
        ];

        $service->create($data);
    }

    public function testErrorOccurredFromArticleContentExtractor(): void
    {
        $contentExtractor = $this->createMock(ArticleContentExtractor::class);
        $contentExtractor->method('extractFromUrl')
            ->willThrowException(new \RuntimeException('Failed to download content'));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        
        $service = new ArticleCreationService(
            $contentExtractor,
            $this->articleRepository,
            $eventDispatcher
        );

        $data = [
            'title' => 'Failed Article',
            'url' => 'https://example.com/failed',
        ];

        $this->expectException(CorruptedArticleContentException::class);
        $this->expectExceptionMessage('Failed to fetch content from URL');

        $service->create($data);
    }

    public function testEmptyContentThrowsCorruptedException(): void
    {
        $contentExtractor = $this->createMock(ArticleContentExtractor::class);
        $contentExtractor->method('extractFromUrl')
            ->willReturn('   ');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        
        $service = new ArticleCreationService(
            $contentExtractor,
            $this->articleRepository,
            $eventDispatcher
        );

        $data = [
            'title' => 'Empty Content Article',
            'url' => 'https://example.com/empty',
        ];

        $this->expectException(CorruptedArticleContentException::class);
        $this->expectExceptionMessage('The downloaded content appears to be empty or corrupted');

        $service->create($data);
    }
}
