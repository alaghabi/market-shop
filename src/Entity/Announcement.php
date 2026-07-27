<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'announcement')]
class Announcement extends AbstractEntity
{
    public const DISPLAY_MODE_FIXED = 'FIXED';
    public const DISPLAY_MODE_SCROLLING = 'SCROLLING';
    public const DISPLAY_MODE_SLIDER = 'SLIDER';

    public const TYPE_TOP_BAR = 'TOP_BAR';
    public const TYPE_BANNER = 'BANNER';
    public const TYPE_POPUP = 'POPUP';
    public const TYPE_SLIDER = 'SLIDER';
    public const TYPE_HOME_SLIDER = 'HOME_SLIDER';
    public const TYPE_ALERT = 'ALERT';
    public const TYPE_INFO_MESSAGE = 'INFO_MESSAGE';

    public const POSITION_HEADER_TOP = 'HEADER_TOP';
    public const POSITION_HEADER_BOTTOM = 'HEADER_BOTTOM';
    public const POSITION_HOME_TOP = 'HOME_TOP';
    public const POSITION_HOME_MIDDLE = 'HOME_MIDDLE';
    public const POSITION_HOME_BOTTOM = 'HOME_BOTTOM';
    public const POSITION_CATEGORY_TOP = 'CATEGORY_TOP';
    public const POSITION_PRODUCT_TOP = 'PRODUCT_TOP';
    public const POSITION_PRODUCT_BOTTOM = 'PRODUCT_BOTTOM';
    public const POSITION_CART_TOP = 'CART_TOP';
    public const POSITION_CHECKOUT_TOP = 'CHECKOUT_TOP';
    public const POSITION_FOOTER = 'FOOTER';
    public const POSITION_TOP_PAGE = self::POSITION_HEADER_TOP;
    public const POSITION_ABOVE_HEADER = self::POSITION_HEADER_TOP;
    public const POSITION_BELOW_HEADER = self::POSITION_HEADER_BOTTOM;
    public const POSITION_ABOVE_FOOTER = self::POSITION_FOOTER;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Boutique::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        private ?Boutique $boutique = null,
        #[ORM\Column(type: Types::TEXT)]
        private string $content = '',
        #[ORM\Column(length: 20)]
        private string $displayType = 'banner',
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $title = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $subtitle = null,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $backgroundColor = null,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $textColor = null,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $borderColor = null,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $buttonColor = null,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $icon = null,
        #[ORM\ManyToOne(targetEntity: Media::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Media $image = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $buttonText = null,
        #[ORM\Column(length: 500, nullable: true)]
        private ?string $linkUrl = null,
        #[ORM\Column]
        private int $priority = 0,
        #[ORM\Column]
        private bool $isDismissible = true,
        #[ORM\Column(length: 20)]
        private string $displayMode = self::DISPLAY_MODE_FIXED,
        #[ORM\Column(length: 30)]
        private string $position = self::POSITION_HEADER_TOP,
        #[ORM\Column(type: Types::JSON)]
        private array $displayPages = ['all'],
        #[ORM\Column(type: Types::JSON)]
        private array $targetCategoryIds = [],
        #[ORM\Column(type: Types::JSON)]
        private array $targetProductIds = [],
        #[ORM\Column(type: Types::JSON)]
        private array $settings = [],
        #[ORM\Column]
        private bool $active = true,
        #[ORM\Column]
        private bool $isGlobal = false,
        #[ORM\Column]
        private int $viewsCount = 0,
        #[ORM\Column]
        private int $clicksCount = 0,
        #[ORM\Column]
        private int $conversionCount = 0,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $updatedAt = null,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $startsAt = null,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $endsAt = null,
    ) {
        parent::__construct();
    }

    public function getBoutique(): ?Boutique
    {
        return $this->boutique;
    }

    public function setBoutique(?Boutique $boutique): void
    {
        $this->boutique = $boutique;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getDisplayType(): string
    {
        return $this->displayType;
    }

    public function setDisplayType(string $displayType): void
    {
        $this->displayType = $displayType;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
        $this->touch();
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): void
    {
        $this->subtitle = $subtitle;
        $this->touch();
    }

    public function getBackgroundColor(): ?string
    {
        return $this->backgroundColor;
    }

    public function setBackgroundColor(?string $backgroundColor): void
    {
        $this->backgroundColor = $backgroundColor;
        $this->touch();
    }

    public function getTextColor(): ?string
    {
        return $this->textColor;
    }

    public function setTextColor(?string $textColor): void
    {
        $this->textColor = $textColor;
        $this->touch();
    }

    public function getBorderColor(): ?string
    {
        return $this->borderColor;
    }

    public function setBorderColor(?string $borderColor): void
    {
        $this->borderColor = $borderColor;
        $this->touch();
    }

    public function getButtonColor(): ?string
    {
        return $this->buttonColor;
    }

    public function setButtonColor(?string $buttonColor): void
    {
        $this->buttonColor = $buttonColor;
        $this->touch();
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
        $this->touch();
    }

    public function getImage(): ?Media
    {
        return $this->image;
    }

    public function setImage(?Media $image): void
    {
        $this->image = $image;
        $this->touch();
    }

    public function getButtonText(): ?string
    {
        return $this->buttonText;
    }

    public function setButtonText(?string $buttonText): void
    {
        $this->buttonText = $buttonText;
        $this->touch();
    }

    public function getLinkUrl(): ?string
    {
        return $this->linkUrl;
    }

    public function setLinkUrl(?string $linkUrl): void
    {
        $this->linkUrl = $linkUrl;
        $this->touch();
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
        $this->touch();
    }

    public function isDismissible(): bool
    {
        return $this->isDismissible;
    }

    public function setIsDismissible(bool $isDismissible): void
    {
        $this->isDismissible = $isDismissible;
        $this->touch();
    }

    public function getDisplayMode(): string
    {
        return $this->displayMode;
    }

    public function setDisplayMode(string $displayMode): void
    {
        $this->displayMode = $displayMode;
        $this->touch();
    }

    public function getPosition(): string
    {
        return $this->position;
    }

    public function setPosition(string $position): void
    {
        $this->position = $position;
        $this->touch();
    }

    public function getDisplayPages(): array
    {
        return $this->displayPages;
    }

    public function setDisplayPages(array $displayPages): void
    {
        $this->displayPages = $displayPages;
        $this->touch();
    }

    public function getTargetCategoryIds(): array
    {
        return $this->targetCategoryIds;
    }

    public function setTargetCategoryIds(array $targetCategoryIds): void
    {
        $this->targetCategoryIds = $targetCategoryIds;
        $this->touch();
    }

    public function getTargetProductIds(): array
    {
        return $this->targetProductIds;
    }

    public function setTargetProductIds(array $targetProductIds): void
    {
        $this->targetProductIds = $targetProductIds;
        $this->touch();
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
        $this->touch();
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
        $this->touch();
    }

    public function isGlobal(): bool
    {
        return $this->isGlobal;
    }

    public function setIsGlobal(bool $isGlobal): void
    {
        $this->isGlobal = $isGlobal;
        $this->touch();
    }

    public function getViewsCount(): int
    {
        return $this->viewsCount;
    }

    public function getClicksCount(): int
    {
        return $this->clicksCount;
    }

    public function getConversionCount(): int
    {
        return $this->conversionCount;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): void
    {
        $this->startsAt = $startsAt;
        $this->touch();
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): void
    {
        $this->endsAt = $endsAt;
        $this->touch();
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isVisible(): bool
    {
        if (!$this->active) {
            return false;
        }

        $now = new \DateTimeImmutable();

        if (null !== $this->startsAt && $now < $this->startsAt) {
            return false;
        }

        if (null !== $this->endsAt && $now > $this->endsAt) {
            return false;
        }

        return true;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
