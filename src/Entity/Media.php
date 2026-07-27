<?php

namespace App\Entity;

use App\Enum\MediaType;
use App\Repository\MediaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'media')]
#[ORM\Index(name: 'idx_media_boutique_type', columns: ['boutique_id', 'type'])]
class Media extends AbstractEntity
{
    public function __construct(
        #[ORM\ManyToOne(targetEntity: Boutique::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Boutique $boutique,
        #[ORM\Column(length: 32, enumType: MediaType::class)]
        private MediaType $type,
        #[ORM\Column(length: 255)]
        private string $originalName,
        #[ORM\Column(length: 255)]
        private string $fileName,
        #[ORM\Column(length: 16)]
        private string $extension,
        #[ORM\Column(length: 64)]
        private string $mimeType,
        #[ORM\Column]
        private int $size = 0,
        #[ORM\Column(nullable: true)]
        private ?int $width = null,
        #[ORM\Column(nullable: true)]
        private ?int $height = null,
        #[ORM\Column(nullable: true)]
        private ?float $duration = null,
        #[ORM\Column(length: 255)]
        private string $path = '',
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $thumbnailPath = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $compressedPath = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $altText = null,
        #[ORM\Column]
        private bool $isPublic = true,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $updatedAt = null,
    ) {
        parent::__construct();
    }

    public function getBoutique(): Boutique
    {
        return $this->boutique;
    }

    public function getType(): MediaType
    {
        return $this->type;
    }

    public function setType(MediaType $type): void
    {
        $this->type = $type;
        $this->touch();
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): void
    {
        $this->width = $width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): void
    {
        $this->height = $height;
    }

    public function getDuration(): ?float
    {
        return $this->duration;
    }

    public function setDuration(?float $duration): void
    {
        $this->duration = $duration;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getThumbnailPath(): ?string
    {
        return $this->thumbnailPath;
    }

    public function setThumbnailPath(?string $path): void
    {
        $this->thumbnailPath = $path;
        $this->touch();
    }

    public function getCompressedPath(): ?string
    {
        return $this->compressedPath;
    }

    public function setCompressedPath(?string $path): void
    {
        $this->compressedPath = $path;
        $this->touch();
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function setAltText(?string $altText): void
    {
        $this->altText = $altText;
        $this->touch();
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): void
    {
        $this->isPublic = $isPublic;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getUrl(): string
    {
        return sprintf('/uploads/%s', ltrim($this->path, '/'));
    }

    public function getThumbnailUrl(): ?string
    {
        return null !== $this->thumbnailPath ? sprintf('/uploads/%s', ltrim($this->thumbnailPath, '/')) : null;
    }

    public function getCompressedUrl(): ?string
    {
        return null !== $this->compressedPath ? sprintf('/uploads/%s', ltrim($this->compressedPath, '/')) : null;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
