<?php

namespace App\Entity;

use App\Doctrine\Traits\SoftDeleteTrait;
use App\Entity\Contract\SoftDeletableInterface;
use App\Enum\BoutiqueStatus;
use App\Repository\BoutiqueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BoutiqueRepository::class)]
#[ORM\Table(name: 'boutique')]
#[ORM\UniqueConstraint(name: 'uniq_boutique_slug', columns: ['slug'])]
class Boutique extends AbstractEntity implements SoftDeletableInterface
{
    use SoftDeleteTrait;

    #[ORM\Column(length: 160)]
    private string $name;

    #[ORM\Column(length: 180)]
    private string $slug;

    #[ORM\Column(length: 32, enumType: BoutiqueStatus::class)]
    private BoutiqueStatus $status = BoutiqueStatus::Pending;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImage = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customDomain = null;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column]
    private bool $isFeatured = false;

    #[ORM\Column]
    private bool $isPublished = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $approvedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\OneToOne(mappedBy: 'boutique', targetEntity: BoutiqueSettings::class, cascade: ['persist', 'remove'])]
    private ?BoutiqueSettings $settings = null;

    #[ORM\ManyToOne(targetEntity: Subscription::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Subscription $currentSubscription = null;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'administeredBoutiques')]
    private Collection $users;

    /** @var Collection<int, Customer> */
    #[ORM\OneToMany(mappedBy: 'boutique', targetEntity: Customer::class, orphanRemoval: true)]
    private Collection $customers;

    /** @var Collection<int, Category> */
    #[ORM\OneToMany(mappedBy: 'boutique', targetEntity: Category::class, orphanRemoval: true)]
    private Collection $categories;

    /** @var Collection<int, Product> */
    #[ORM\OneToMany(mappedBy: 'boutique', targetEntity: Product::class, orphanRemoval: true)]
    private Collection $products;

    /** @var Collection<int, Subscription> */
    #[ORM\OneToMany(mappedBy: 'boutique', targetEntity: Subscription::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $subscriptions;

    /** @var Collection<int, Review> */
    #[ORM\OneToMany(mappedBy: 'boutique', targetEntity: Review::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $reviews;

    /** @var Collection<int, BoutiqueDeliveryAccount> */
    #[ORM\OneToMany(mappedBy: 'boutique', targetEntity: BoutiqueDeliveryAccount::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $deliveryAccounts;

    /** @var Collection<int, UserShop> */
    #[ORM\OneToMany(mappedBy: 'boutique', targetEntity: UserShop::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $userShops;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(string $name, string $slug)
    {
        parent::__construct();
        $this->name = $name;
        $this->slug = $slug;
        $this->users = new ArrayCollection();
        $this->customers = new ArrayCollection();
        $this->categories = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->subscriptions = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->deliveryAccounts = new ArrayCollection();
        $this->userShops = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getStatus(): BoutiqueStatus
    {
        return $this->status;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
        $this->touch();
    }

    public function setStatus(BoutiqueStatus $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): void
    {
        $this->owner = $owner;
        $this->touch();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(?string $coverImage): void
    {
        $this->coverImage = $coverImage;
        $this->touch();
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
        $this->touch();
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
        $this->touch();
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): void
    {
        $this->website = $website;
        $this->touch();
    }

    public function getCustomDomain(): ?string
    {
        return $this->customDomain;
    }

    public function setCustomDomain(?string $customDomain): void
    {
        $this->customDomain = $customDomain;
        $this->touch();
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): void
    {
        $this->isVerified = $isVerified;
        $this->touch();
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): void
    {
        $this->isFeatured = $isFeatured;
        $this->touch();
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function publish(): void
    {
        $this->isPublished = true;
        $this->touch();
    }

    public function unpublish(): void
    {
        $this->isPublished = false;
        $this->touch();
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function getApprovedBy(): ?string
    {
        return $this->approvedBy;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $reason): void
    {
        $this->rejectionReason = $reason;
        $this->touch();
    }

    public function approve(?string $approvedBy = null): void
    {
        $this->status = BoutiqueStatus::Active;
        $this->approvedAt = new \DateTimeImmutable();
        $this->approvedBy = $approvedBy;
        $this->touch();
    }

    public function reject(?string $reason = null): void
    {
        $this->status = BoutiqueStatus::Rejected;
        $this->rejectionReason = $reason;
        $this->touch();
    }

    public function suspend(): void
    {
        $this->status = BoutiqueStatus::Suspended;
        $this->touch();
    }

    public function archive(): void
    {
        $this->status = BoutiqueStatus::Archived;
        $this->touch();
    }

    public function reactivate(): void
    {
        $this->status = BoutiqueStatus::Active;
        $this->touch();
    }

    public function getSettings(): ?BoutiqueSettings
    {
        return $this->settings;
    }

    public function setSettings(BoutiqueSettings $settings): void
    {
        $this->settings = $settings;
    }

    public function getCurrentSubscription(): ?Subscription
    {
        return $this->currentSubscription;
    }

    public function setCurrentSubscription(?Subscription $subscription): void
    {
        $this->currentSubscription = $subscription;
        $this->touch();
    }

    public function getPrimaryColor(): string
    {
        return $this->settings?->getPrimaryColor() ?? '#3525cd';
    }

    public function getSecondaryColor(): string
    {
        return $this->settings?->getSecondaryColor() ?? '#505f76';
    }

    public function getDomain(): ?string
    {
        return $this->customDomain ?? $this->settings?->getDomain();
    }

    public function getLogoUrl(): ?string
    {
        return $this->settings?->getLogoUrl();
    }

    public function getContactEmail(): ?string
    {
        return $this->email ?? $this->settings?->getContactEmail();
    }

    public function getContactPhone(): ?string
    {
        return $this->phone ?? $this->settings?->getContactPhone();
    }

    public function getAddress(): ?string
    {
        return $this->settings?->getAddress();
    }

    /** @return array<string, string> */
    public function getSocialLinks(): array
    {
        return $this->settings?->getSocialLinks() ?? [];
    }

    public function getMetaPixelId(): ?string
    {
        return $this->settings?->getMetaPixelId();
    }

    public function setMetaPixelId(?string $metaPixelId): void
    {
        $this->settings?->setMetaPixelId($metaPixelId);
    }

    public function getGoogleAnalyticsId(): ?string
    {
        return $this->settings?->getGoogleAnalyticsId();
    }

    public function getGoogleTagManagerId(): ?string
    {
        return $this->settings?->getGoogleTagManagerId();
    }

    public function getTiktokPixelId(): ?string
    {
        return $this->settings?->getTiktokPixelId();
    }

    public function getCheckoutMode(): string
    {
        return $this->settings?->getCheckoutMode()->value ?? 'ACCOUNT_ONLY';
    }

    public function isCreateAccountAfterOrder(): bool
    {
        return $this->settings?->isCreateAccountAfterOrder() ?? false;
    }

    public function isEnableCustomerEmailVerification(): bool
    {
        return $this->settings?->isEnableCustomerEmailVerification() ?? false;
    }

    /** @return Collection<int, User> */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    /** @return Collection<int, Product> */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function getOrdersCount(): int
    {
        return 0;
    }

    public function getTotalRevenue(): float
    {
        return 0.0;
    }

    public function touchFromSettings(): void
    {
        $this->touch();
    }

    /** @return Collection<int, BoutiqueDeliveryAccount> */
    public function getDeliveryAccounts(): Collection
    {
        return $this->deliveryAccounts;
    }

    /** @return Collection<int, UserShop> */
    public function getUserShops(): Collection
    {
        return $this->userShops;
    }

    public function hasActiveSubscription(): bool
    {
        if (null !== $this->currentSubscription && \App\Enum\SubscriptionStatus::Active === $this->currentSubscription->getStatus()) {
            $now = new \DateTimeImmutable();

            return null === $this->currentSubscription->getEndDate() || $this->currentSubscription->getEndDate() > $now;
        }

        foreach ($this->subscriptions as $subscription) {
            if (\App\Enum\SubscriptionStatus::Active === $subscription->getStatus()) {
                return true;
            }
        }

        return false;
    }

    public function isVisiblePublicly(): bool
    {
        if (BoutiqueStatus::Active !== $this->status) {
            return false;
        }

        return $this->isPublished && $this->hasActiveSubscription();
    }

    public function getSubdomainUrl(string $rootDomain): string
    {
        return sprintf('https://%s.%s', $this->slug, trim($rootDomain, '.'));
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt ?? $this->createdAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
