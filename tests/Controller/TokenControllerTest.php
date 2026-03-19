<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TokenControllerTest extends WebTestCase
{
    private KernelBrowser $browser;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->browser = static::createClient();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Client')->execute();
    }

    private function createApiClient(string $clientId, string $plainSecret): void
    {
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $client = new Client();
        $client->setClientId($clientId);
        $client->setClientSecret($passwordHasher->hashPassword($client, $plainSecret));
        $client->setScopes(['article:read', 'article:write']);

        $this->entityManager->persist($client);
        $this->entityManager->flush();
    }

    public function testTokenIssuedForValidCredentials(): void
    {
        $this->createApiClient('my-client-id', 'my-client-secret');

        $this->browser->request(
            'POST',
            '/api/token',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['client_id' => 'my-client-id', 'client_secret' => 'my-client-secret'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $payload = json_decode($this->browser->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $payload);
        $this->assertNotEmpty($payload['token']);
    }

    public function testTokenIsRejectedForInvalidSecret(): void
    {
        $this->createApiClient('my-client-id', 'correct-secret');

        $this->browser->request(
            'POST',
            '/api/token',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['client_id' => 'my-client-id', 'client_secret' => 'wrong-secret'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $payload = json_decode($this->browser->getResponse()->getContent(), true);
        $this->assertSame('Invalid credentials', $payload['error']);
    }

    public function testTokenIsRejectedForUnknownClientId(): void
    {
        $this->browser->request(
            'POST',
            '/api/token',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['client_id' => 'unknown-client', 'client_secret' => 'any-secret'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $payload = json_decode($this->browser->getResponse()->getContent(), true);
        $this->assertSame('Invalid credentials', $payload['error']);
    }

    public function testTokenRequiresBothFields(): void
    {
        $this->browser->request(
            'POST',
            '/api/token',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['client_id' => 'my-client-id'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $payload = json_decode($this->browser->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $payload);
    }
}
