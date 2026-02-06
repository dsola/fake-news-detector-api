<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

class ArticleApiTest extends ApiTestCase
{
    public function testGetCollection(): void
    {
        // Create a client
        $response = static::createClient()->request('GET', '/api/articles');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/api/contexts/Article',
            '@id' => '/api/articles',
            '@type' => 'Collection',
        ]);
    }

    public function testCreateArticle(): void
    {
        $response = static::createClient()->request('POST', '/api/articles', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'url' => 'https://example.com/article',
                'title' => 'Test Article',
                'content' => 'This is a test article content.',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/api/contexts/Article',
            '@type' => 'Article',
            'url' => 'https://example.com/article',
            'title' => 'Test Article',
            'content' => 'This is a test article content.',
            'verificationStatus' => 'pending',
        ]);
    }

    public function testCreateInvalidArticle(): void
    {
        static::createClient()->request('POST', '/api/articles', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'url' => 'invalid-url',
                'title' => 'T',
                'content' => '',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
        ]);
    }

    public function testGetArticle(): void
    {
        $client = static::createClient();
        
        // Create an article first
        $response = $client->request('POST', '/api/articles', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'url' => 'https://example.com/article2',
                'title' => 'Another Test Article',
                'content' => 'This is another test article content.',
            ],
        ]);
        
        $this->assertResponseStatusCodeSame(201);
        $id = json_decode($response->getContent())->id;

        // Now fetch it
        $client->request('GET', '/api/articles/' . $id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/api/contexts/Article',
            '@type' => 'Article',
            'id' => $id,
            'url' => 'https://example.com/article2',
            'title' => 'Another Test Article',
        ]);
    }
}
