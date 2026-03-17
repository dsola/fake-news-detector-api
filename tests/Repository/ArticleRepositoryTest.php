<?php

namespace App\Tests\Repository;

use App\Entity\Article;
use App\Entity\Verification;
use App\Entity\VerificationResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ArticleRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();
        
        // Clean up
        $this->entityManager->createQuery('DELETE FROM App\Entity\Verification')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Article')->execute();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    public function testPersistArticleWithAllDateTimeFields(): void
    {
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setUrl('https://example.com');
        $article->setContent('Test content');
        
        // Test setting datetime fields with DateTime objects
        $now = new \DateTime('2026-02-15 12:00:00');
        $article->setVerifiedAt($now);
        $article->setErroredAt($now);
        
        $this->entityManager->persist($article);
        $this->entityManager->flush();
        
        $id = $article->getId();
        
        // Clear the entity manager to ensure fresh data is loaded
        $this->entityManager->clear();
        
        // Retrieve and verify
        $retrieved = $this->entityManager->getRepository(Article::class)->find($id);
        
        $this->assertNotNull($retrieved);
        $this->assertSame('Test Article', $retrieved->getTitle());
        $this->assertSame('https://example.com', $retrieved->getUrl());
        $this->assertNotNull($retrieved->getVerifiedAt());
        $this->assertNotNull($retrieved->getErroredAt());
        $this->assertNotNull($retrieved->getCreatedAt());
        $this->assertNotNull($retrieved->getUpdatedAt());
    }

    public function testPersistArticleWithDateTimeImmutableFields(): void
    {
        $article = new Article();
        $article->setTitle('Test Article with Immutable');
        $article->setUrl('https://example.com/immutable');
        $article->setContent('Test content');
        
        // Test setting datetime fields with DateTimeImmutable objects
        // This should be converted to DateTime by the setter
        $immutableNow = new \DateTimeImmutable('2026-02-15 14:30:00');
        $article->setVerifiedAt($immutableNow);
        $article->setErroredAt($immutableNow);
        
        $this->entityManager->persist($article);
        $this->entityManager->flush();
        
        $id = $article->getId();
        
        // Clear the entity manager to ensure fresh data is loaded
        $this->entityManager->clear();
        
        // Retrieve and verify
        $retrieved = $this->entityManager->getRepository(Article::class)->find($id);
        
        $this->assertNotNull($retrieved);
        $this->assertSame('Test Article with Immutable', $retrieved->getTitle());
        $this->assertInstanceOf(\DateTimeInterface::class, $retrieved->getVerifiedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $retrieved->getErroredAt());
    }

    public function testPersistArticleWithVerificationsAndAllDateTimes(): void
    {
        $article = new Article();
        $article->setTitle('Article with Verifications');
        $article->setUrl('https://example.com/verified');
        $article->setContent('Verified content');
        $article->setVerifiedAt(new \DateTime('2026-02-15 10:00:00'));
        
        // Add verification with datetime fields
        $verification = new Verification();
        $verification->setType('SIMILAR_CONTENT');
        $verification->setResult(VerificationResult::APPROVED->value);
        $verification->setMetadata(['reason' => 'test']);
        $verification->setStartedAt(new \DateTime('2026-02-15 09:00:00'));
        $verification->setTerminatedAt(new \DateTime('2026-02-15 10:00:00'));
        
        $article->addVerification($verification);
        
        $this->entityManager->persist($article);
        $this->entityManager->flush();
        
        $id = $article->getId();
        $verificationId = $verification->getId();
        
        // Clear the entity manager to ensure fresh data is loaded
        $this->entityManager->clear();
        
        // Retrieve and verify article
        $retrieved = $this->entityManager->getRepository(Article::class)->find($id);
        
        $this->assertNotNull($retrieved);
        $this->assertSame('Article with Verifications', $retrieved->getTitle());
        $this->assertNotNull($retrieved->getVerifiedAt());
        $this->assertCount(1, $retrieved->getVerifications());
        
        // Verify verification data
        $retrievedVerification = $this->entityManager->getRepository(Verification::class)->find($verificationId);
        
        $this->assertNotNull($retrievedVerification);
        $this->assertSame('SIMILAR_CONTENT', $retrievedVerification->getType());
        $this->assertNotNull($retrievedVerification->getStartedAt());
        $this->assertNotNull($retrievedVerification->getTerminatedAt());
        $this->assertNotNull($retrievedVerification->getCreatedAt());
    }

    public function testPersistArticleWithImmutableVerificationDateTimes(): void
    {
        $article = new Article();
        $article->setTitle('Article with Immutable Verification Dates');
        $article->setUrl('https://example.com/immutable-verified');
        
        // Add verification with immutable datetime fields
        $verification = new Verification();
        $verification->setType('SIMILAR_CONTENT');
        $verification->setResult(VerificationResult::APPROVED->value);
        $verification->setMetadata(['reason' => 'test']);
        
        // Use DateTimeImmutable - should be converted to DateTime by setters
        $immutableStart = new \DateTimeImmutable('2026-02-15 09:00:00');
        $immutableEnd = new \DateTimeImmutable('2026-02-15 10:00:00');
        $verification->setStartedAt($immutableStart);
        $verification->setTerminatedAt($immutableEnd);
        $verification->setErroredAt($immutableEnd);
        
        $article->addVerification($verification);
        
        $this->entityManager->persist($article);
        $this->entityManager->flush();
        
        $id = $article->getId();
        
        // Clear the entity manager to ensure fresh data is loaded
        $this->entityManager->clear();
        
        // Retrieve and verify
        $retrieved = $this->entityManager->getRepository(Article::class)->find($id);
        
        $this->assertNotNull($retrieved);
        $this->assertCount(1, $retrieved->getVerifications());
        
        $retrievedVerification = $retrieved->getVerifications()->first();
        $this->assertInstanceOf(\DateTimeInterface::class, $retrievedVerification->getStartedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $retrievedVerification->getTerminatedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $retrievedVerification->getErroredAt());
    }
}
