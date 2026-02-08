<?php

namespace App\Tests\Controller;

use App\Exception\CorruptedArticleContentException;
use App\Service\ArticleContentExtractor;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ArticleControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        $entityManager->createQuery('DELETE FROM App\Entity\Article')->execute();
    }

    public function testAssertTheContentOfTheJsonResource(): void
    {
        $htmlContent = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <h1>News Article</h1>
    <p>This is news content.</p>
</body>
</html>
HTML;

        $mockResponse = new MockResponse($htmlContent, ['http_code' => 200]);
        $mockHttpClient = new MockHttpClient($mockResponse);
        
        static::getContainer()->set('http_client', $mockHttpClient);
        static::getContainer()->set(ArticleContentExtractor::class, new ArticleContentExtractor($mockHttpClient));

        $client = static::createClient();
        $client->request('POST', '/api/articles', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'title' => 'My Test Article',
                'url' => 'https://example.com/news',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        
        $responseData = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('id', $responseData);
        $this->assertArrayHasKey('title', $responseData);
        $this->assertArrayHasKey('url', $responseData);
        $this->assertArrayHasKey('status', $responseData);
        
        $this->assertEquals('My Test Article', $responseData['title']);
        $this->assertEquals('https://example.com/news', $responseData['url']);
        $this->assertEquals('STARTED', $responseData['status']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $responseData['id']
        );
    }

    public function testAssertTheRespectiveUserValidationInput(): void
    {
        $client = static::createClient();
        
        $client->request('POST', '/api/articles', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'title' => 'AB',
                'url' => 'not-a-valid-url',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        
        $responseData = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('violations', $responseData);
        $violations = $responseData['violations'];
        
        $this->assertGreaterThanOrEqual(1, count($violations));
        
        $titleViolation = null;
        $urlViolation = null;
        
        foreach ($violations as $violation) {
            if ($violation['propertyPath'] === 'title') {
                $titleViolation = $violation;
            }
            if ($violation['propertyPath'] === 'url') {
                $urlViolation = $violation;
            }
        }
        
        $this->assertNotNull($titleViolation);
        $this->assertStringContainsString('at least', $titleViolation['message']);
        
        $this->assertNotNull($urlViolation);
        $this->assertStringContainsString('not valid', $urlViolation['message']);
    }

    public function testAssertMissingRequiredFields(): void
    {
        $client = static::createClient();
        
        $client->request('POST', '/api/articles', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(422);
        
        $responseData = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('violations', $responseData);
        $this->assertGreaterThanOrEqual(2, count($responseData['violations']));
    }

    public function testAssertThatControllerDidHandleTheErrorMessageWhenUrlCouldNotBeDownloaded(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => 500,
            'error' => 'Server Error'
        ]);
        $mockHttpClient = new MockHttpClient($mockResponse);
        
        static::getContainer()->set('http_client', $mockHttpClient);
        static::getContainer()->set(ArticleContentExtractor::class, new ArticleContentExtractor($mockHttpClient));

        $client = static::createClient();
        $client->request('POST', '/api/articles', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'title' => 'Failed Download Article',
                'url' => 'https://example.com/broken-url',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
        
        $responseData = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('error', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertStringContainsString('Failed to fetch content', $responseData['message']);
    }

    public function testAssertEmptyContentIsHandledAsError(): void
    {
        $emptyHtml = <<<HTML
<!DOCTYPE html>
<html>
<head><title>Empty</title></head>
<body>
    <script>console.log('no content');</script>
</body>
</html>
HTML;

        $mockResponse = new MockResponse($emptyHtml, ['http_code' => 200]);
        $mockHttpClient = new MockHttpClient($mockResponse);
        
        static::getContainer()->set('http_client', $mockHttpClient);
        static::getContainer()->set(ArticleContentExtractor::class, new ArticleContentExtractor($mockHttpClient));

        $client = static::createClient();
        $client->request('POST', '/api/articles', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'title' => 'Empty Content Article',
                'url' => 'https://example.com/empty-content',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
        
        $responseData = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('error', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertStringContainsString('empty or corrupted', $responseData['message']);
    }
}
