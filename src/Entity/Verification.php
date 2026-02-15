<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\VerificationRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VerificationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Patch(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['verification:read']],
    denormalizationContext: ['groups' => ['verification:write']]
)]
class Verification
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['verification:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'verifications')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['verification:write'])]
    private ?Article $article = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Groups(['verification:read', 'verification:write'])]
    private ?string $type = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Groups(['verification:read', 'verification:write'])]
    private ?string $result = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['verification:read', 'verification:write'])]
    private array $metadata = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['verification:read'])]
    private ?\DateTimeInterface $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['verification:read'])]
    private ?\DateTimeInterface $terminatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['verification:read'])]
    private ?\DateTimeInterface $erroredAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['verification:read'])]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(string $result): static
    {
        $this->result = $result;
        return $this;
    }

    public function getResultAsEnum(): ?VerificationResult
    {
        if ($this->result === null) {
            return null;
        }
        return VerificationResult::from($this->result);
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->startedAt;
    }

    public function setStartedAt(DateTimeInterface $startedAt): static
    {
        if (!$startedAt instanceof \DateTime) {
            $startedAt = \DateTime::createFromInterface($startedAt);
        }
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getTerminatedAt(): ?\DateTimeInterface
    {
        return $this->terminatedAt;
    }

    public function setTerminatedAt(DateTimeInterface $terminatedAt): static
    {
        if (!$terminatedAt instanceof \DateTime) {
            $terminatedAt = \DateTime::createFromInterface($terminatedAt);
        }
        $this->terminatedAt = $terminatedAt;
        return $this;
    }

    public function getErroredAt(): ?\DateTimeInterface
    {
        return $this->erroredAt;
    }

    public function setErroredAt(DateTimeInterface $erroredAt): static
    {
        if (!$erroredAt instanceof \DateTime) {
            $erroredAt = \DateTime::createFromInterface($erroredAt);
        }
        $this->erroredAt = $erroredAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        if (!$createdAt instanceof \DateTime) {
            $createdAt = \DateTime::createFromInterface($createdAt);
        }
        $this->createdAt = $createdAt;
        return $this;
    }
}
