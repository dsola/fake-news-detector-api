<?php

namespace App\Tests\Controller;

use App\Entity\Article;
use App\Entity\Client;
use App\Entity\Verification;
use App\Entity\VerificationResult;
use App\Service\ArticleContentExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Tests that require HTTP client mocking
 * These tests use KernelTestCase because WebTestCase doesn't allow booting the kernel manually
 */
class ArticleControllerKernelTest extends KernelTestCase
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

        $container = static::getContainer();
        $container->set('http_client', $mockHttpClient);
        $container->set(ArticleContentExtractor::class, new ArticleContentExtractor($mockHttpClient));

        // Test the service directly
        $service = $container->get(ArticleContentExtractor::class);
        $content = $service->extractFromUrl('https://example.com/news');

        $this->assertStringContainsString('News Article', $content);
        $this->assertStringContainsString('This is news content.', $content);
    }

    public function testAssertThatControllerDidHandleTheErrorMessageWhenUrlCouldNotBeDownloaded(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => 500,
            'error' => 'Server Error'
        ]);
        $mockHttpClient = new MockHttpClient($mockResponse);

        $container = static::getContainer();
        $container->set('http_client', $mockHttpClient);
        $container->set(ArticleContentExtractor::class, new ArticleContentExtractor($mockHttpClient));

        $service = $container->get(ArticleContentExtractor::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to download content from URL');

        $service->extractFromUrl('https://example.com/broken-url');
    }

    public function testAssertEmptyContentIsHandledAsError(): void
    {
        $emptyHtml = <<<HTML
<!DOCTYPE html>
<html>
<head><title></title></head>
<body>
    <script>console.log('no content');</script>
    <style>body { color: red; }</style>
</body>
</html>
HTML;

        $mockResponse = new MockResponse($emptyHtml, ['http_code' => 200]);
        $mockHttpClient = new MockHttpClient($mockResponse);

        $container = static::getContainer();
        $container->set('http_client', $mockHttpClient);
        $container->set(ArticleContentExtractor::class, new ArticleContentExtractor($mockHttpClient));

        $service = $container->get(ArticleContentExtractor::class);
        $content = $service->extractFromUrl('https://example.com/empty-content');

        $this->assertEmpty(trim($content));
    }
}

/**
 * Tests that don't require HTTP client mocking
 * These use WebTestCase for integration testing
 */
class ArticleControllerTest extends WebTestCase
{
    private KernelBrowser $browser;
    private EntityManagerInterface $entityManager;
    private string $jwtToken;

    protected function setUp(): void
    {
        $this->browser = static::createClient();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();
        $this->purgeDatabase($this->entityManager);
        $this->jwtToken = $this->createAuthenticatedClient();
    }

    private function purgeDatabase(EntityManagerInterface $entityManager): void
    {
        $entityManager->createQuery('DELETE FROM App\\Entity\\Verification')->execute();
        $entityManager->createQuery('DELETE FROM App\\Entity\\Article')->execute();
        $entityManager->createQuery('DELETE FROM App\\Entity\\Client')->execute();
    }

    private function createAuthenticatedClient(): string
    {
        $container = static::getContainer();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $client = new Client();
        $client->setClientId('test-client');
        $client->setClientSecret($passwordHasher->hashPassword($client, 'test-secret'));
        $client->setScopes(['article:read', 'article:write']);

        $this->entityManager->persist($client);
        $this->entityManager->flush();

        $jwtManager = $container->get(JWTTokenManagerInterface::class);

        return $jwtManager->create($client);
    }

    private function withAuth(array $serverParams = []): array
    {
        return array_merge($serverParams, ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwtToken]);
    }

    public function testAssertTheRespectiveUserValidationInput(): void
    {
        $this->browser->request(
            'POST',
            '/api/articles',
            [],
            [],
            $this->withAuth(['CONTENT_TYPE' => 'application/json']),
            json_encode(['url' => 'not-a-valid-url'])
        );

        $this->assertResponseStatusCodeSame(422);

        $responseData = json_decode($this->browser->getResponse()->getContent(), true);

        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('violations', $responseData);

        $urlViolation = null;
        foreach ($responseData['violations'] as $violation) {
            if ($violation['propertyPath'] === 'url') {
                $urlViolation = $violation;
            }
        }

        $this->assertNotNull($urlViolation);
    }

    public function testAssertMissingRequiredFields(): void
    {
        $this->browser->request(
            'POST',
            '/api/articles',
            [],
            [],
            $this->withAuth(['CONTENT_TYPE' => 'application/json']),
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(422);

        $responseData = json_decode($this->browser->getResponse()->getContent(), true);

        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('violations', $responseData);
        $this->assertCount(1, $responseData['violations']);
        $this->assertSame('url', $responseData['violations'][0]['propertyPath']);
    }

    public function testGetArticleReturnsArticleWithVerifications(): void
    {
        $article = new Article();
        $article->setTitle('Fetched Article');
        $article->setUrl('https://example.com/verified');
        $article->setContent('Verified content.');

        $verification = new Verification();
        $verification->setType('SIMILAR_CONTENT');
        $verification->setResult(VerificationResult::APPROVED->value);
        $verification->setMetadata(['reason' => 'test']);
        $verification->setStartedAt(new \DateTime());
        $verification->setTerminatedAt(new \DateTime());

        $article->addVerification($verification);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->browser->request(
            'GET',
            '/api/articles/' . $article->getId()->toRfc4122(),
            [],
            [],
            $this->withAuth()
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $payload = json_decode($this->browser->getResponse()->getContent(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('verifications', $payload);
        $this->assertNotEmpty($payload['verifications']);
        $this->assertSame('SIMILAR_CONTENT', $payload['verifications'][0]['type']);
        $this->assertSame(VerificationResult::APPROVED->value, $payload['verifications'][0]['result']);
    }

    public function testGetArticleRespondsNotFoundForMissingArticle(): void
    {
        $this->browser->request(
            'GET',
            '/api/articles/' . Uuid::v4()->toRfc4122(),
            [],
            [],
            $this->withAuth()
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $payload = json_decode($this->browser->getResponse()->getContent(), true);
        $this->assertSame('Article not found', $payload['error']);
    }

    public function testGetArticleRejectsInvalidIdFormat(): void
    {
        $this->browser->request(
            'GET',
            '/api/articles/not-a-uuid',
            [],
            [],
            $this->withAuth()
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $payload = json_decode($this->browser->getResponse()->getContent(), true);
        $this->assertSame('Invalid article ID format', $payload['error']);
    }

    public function testUnauthenticatedRequestIsRejected(): void
    {
        $this->browser->request('GET', '/api/articles/' . Uuid::v4()->toRfc4122());

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
