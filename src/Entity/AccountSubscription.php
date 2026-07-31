<?php

namespace App\Entity;

use App\Enum\AccountSubscriptionStatus;
use App\Repository\AccountSubscriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccountSubscriptionRepository::class)]
#[ORM\Table(name: 'account_subscription')]
class AccountSubscription extends AbstractEntity
{
    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'accountSubscriptions')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\ManyToOne(targetEntity: SubscriptionPlan::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?SubscriptionPlan $subscriptionPlan,
        #[ORM\Column(length: 32, enumType: AccountSubscriptionStatus::class)]
        private AccountSubscriptionStatus $status = AccountSubscriptionStatus::Active,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $startDate = null,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $endDate = null,
        #[ORM\ManyToOne(targetEntity: self::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?self $replacedBy = null,
        #[ORM\Column(length: 180, nullable: true)]
        private ?string $changedBy = null,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $updatedAt = null,
        /** @var Collection<int, AccountSubscriptionExtension>|null */
        #[ORM\OneToMany(mappedBy: 'accountSubscription', targetEntity: AccountSubscriptionExtension::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
        private ?Collection $extensions = null,
    ) {
        $this->extensions = $this->extensions ?? new ArrayCollection();
        parent::__construct();
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getSubscriptionPlan(): ?SubscriptionPlan
    {
        return $this->subscriptionPlan;
    }

    public function setSubscriptionPlan(?SubscriptionPlan $subscriptionPlan): void
    {
        $this->subscriptionPlan = $subscriptionPlan;
    }

    public function getStatus(): AccountSubscriptionStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function getReplacedBy(): ?self
    {
        return $this->replacedBy;
    }

    public function getChangedBy(): ?string
    {
        return $this->changedBy;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, AccountSubscriptionExtension> */
    public function getExtensions(): Collection
    {
        return $this->extensions;
    }

    public function addExtension(AccountSubscriptionExtension $extension): void
    {
        if (!$this->extensions->contains($extension)) {
            $this->extensions->add($extension);
        }
    }

    public function activate(string $changedBy): void
    {
        $this->activateFrom(new \DateTimeImmutable(), $changedBy);
    }

    public function activateFrom(\DateTimeImmutable $baseDate, string $changedBy): void
    {
        $now = new \DateTimeImmutable();
        $this->status = AccountSubscriptionStatus::Active;
        $this->startDate = $now;
        $plan = $this->subscriptionPlan;
        $this->endDate = null !== $plan && $plan->getDurationMonths() > 0
            ? $baseDate->modify(sprintf('+%d months', $plan->getDurationMonths()))
            : null;
        $this->changedBy = $changedBy;
        $this->updatedAt = $now;
    }

    public function activateWithDates(\DateTimeImmutable $startDate, ?\DateTimeImmutable $endDate, string $changedBy): void
    {
        $this->status = AccountSubscriptionStatus::Active;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->changedBy = $changedBy;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markAsExpired(): void
    {
        $this->status = AccountSubscriptionStatus::Expired;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markAsReplaced(self $replacedBy): void
    {
        $this->status = AccountSubscriptionStatus::Replaced;
        $this->replacedBy = $replacedBy;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
