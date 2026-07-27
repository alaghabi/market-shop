<?php

namespace App\Entity;

use App\Enum\ReviewStatus;
use App\Repository\ReviewRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Table(name: 'review')]
class Review extends AbstractEntity
{
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Boutique::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Boutique $boutique = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(length: 120)]
    private string $authorName;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $authorEmail = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $authorPhone = null;

    #[ORM\Column(type: 'smallint')]
    private int $rating;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'json')]
    private array $images = [];

    #[ORM\Column]
    private bool $verifiedPurchase = false;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ipHash = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $browserHash = null;

    #[ORM\Column(length: 16, enumType: ReviewStatus::class)]
    private ReviewStatus $status = ReviewStatus::Pending;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(
        ?Boutique $boutique,
        ?Product $product,
        string $authorName,
        int $rating,
        ?string $comment = null,
    ) {
        parent::__construct();
        $this->boutique = $boutique;
        $this->product = $product;
        $this->authorName = $authorName;
        $this->rating = $rating;
        $this->comment = $comment;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getBoutique(): ?Boutique
    {
        return $this->boutique;
    }

    public function setBoutique(?Boutique $boutique): void
    {
        $this->boutique = $boutique;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): void
    {
        $this->product = $product;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $authorName): void
    {
        $this->authorName = $authorName;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getAuthorEmail(): ?string
    {
        return $this->authorEmail;
    }

    public function setAuthorEmail(?string $authorEmail): void
    {
        $this->authorEmail = $authorEmail;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getAuthorPhone(): ?string
    {
        return $this->authorPhone;
    }

    public function setAuthorPhone(?string $authorPhone): void
    {
        $this->authorPhone = $authorPhone;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): void
    {
        $this->rating = $rating;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function setImages(array $images): void
    {
        $this->images = $images;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isVerifiedPurchase(): bool
    {
        return $this->verifiedPurchase;
    }

    public function setVerifiedPurchase(bool $verifiedPurchase): void
    {
        $this->verifiedPurchase = $verifiedPurchase;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getIpHash(): ?string
    {
        return $this->ipHash;
    }

    public function setIpHash(?string $ipHash): void
    {
        $this->ipHash = $ipHash;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getBrowserHash(): ?string
    {
        return $this->browserHash;
    }

    public function setBrowserHash(?string $browserHash): void
    {
        $this->browserHash = $browserHash;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getStatus(): ReviewStatus
    {
        return $this->status;
    }

    public function approve(): void
    {
        $this->status = ReviewStatus::Approved;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function reject(): void
    {
        $this->status = ReviewStatus::Rejected;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
