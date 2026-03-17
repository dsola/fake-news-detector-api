<?php

namespace App\Entity;

use App\Repository\ArticleRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Article
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['article:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    #[Groups(['article:read', 'article:write'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['article:read', 'article:write'])]
    private ?string $content = null;

    #[ORM\Column(length: 255)]
    #[Groups(['article:read', 'article:write'])]
    private ?string $url = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['article:read'])]
    private ?\DateTimeInterface $verifiedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['article:read'])]
    private ?\DateTimeInterface $erroredAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['article:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['article:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: Verification::class, mappedBy: 'article', cascade: ['persist', 'remove'])]
    #[Groups(['article:read'])]
    private Collection $verifications;

    #[ORM\OneToMany(targetEntity: SimilarArticle::class, mappedBy: 'article', cascade: ['persist', 'remove'])]
    private Collection $similarArticles;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->verifications = new ArrayCollection();
        $this->similarArticles = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = mb_convert_encoding($content, 'UTF-8');
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeInterface
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(DateTimeInterface $verifiedAt): static
    {
        if (!$verifiedAt instanceof \DateTime) {
            $verifiedAt = \DateTime::createFromInterface($verifiedAt);
        }
        $this->verifiedAt = $verifiedAt;
        return $this;
    }

    public function getErroredAt(): ?DateTimeInterface
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        if (!$updatedAt instanceof \DateTime) {
            $updatedAt = \DateTime::createFromInterface($updatedAt);
        }
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, Verification>
     */
    public function getVerifications(): Collection
    {
        return $this->verifications;
    }

    public function addVerification(Verification $verification): static
    {
        if (!$this->verifications->contains($verification)) {
            $this->verifications->add($verification);
            $verification->setArticle($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, SimilarArticle>
     */
    public function getSimilarArticles(): Collection
    {
        return $this->similarArticles;
    }

    public function addSimilarArticle(SimilarArticle $similarArticle): static
    {
        if (!$this->similarArticles->contains($similarArticle)) {
            $this->similarArticles->add($similarArticle);
            $similarArticle->setArticle($this);
        }
        return $this;
    }
}
