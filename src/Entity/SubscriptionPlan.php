<?php

namespace App\Entity;

use App\Repository\SubscriptionPlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionPlanRepository::class)]
#[ORM\Table(name: 'subscription_plan')]
class SubscriptionPlan extends AbstractEntity
{
    public function __construct(
        #[ORM\Column(length: 160)]
        private string $name,
        #[ORM\Column]
        private int $durationMonths,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $description = null,
        #[ORM\Column]
        private int $priceTnd = 0,
        /** Prix de renouvellement en millimes. Null = utiliser priceTnd. */
        #[ORM\Column(nullable: true)]
        private ?int $renewalPriceTnd = null,
        #[ORM\Column]
        private bool $isFree = false,
        #[ORM\Column]
        private bool $isVisible = true,
        #[ORM\Column]
        private bool $isActive = true,
        #[ORM\Column(type: 'json', nullable: true)]
        private ?array $modules = null,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $chatbotModel = null,
        #[ORM\Column(length: 8)]
        private string $currency = 'TND',
        #[ORM\Column]
        private int $displayOrder = 0,

        /** @var Collection<int, SubscriptionModule>|null */
        #[ORM\OneToMany(mappedBy: 'plan', targetEntity: SubscriptionModule::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
        private ?Collection $subscriptionModules = null,
        /** @var Collection<int, PlanQuota>|null */
        #[ORM\OneToMany(mappedBy: 'plan', targetEntity: PlanQuota::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
        private ?Collection $planQuotas = null,
        /** @var Collection<int, Theme>|null */
        #[ORM\ManyToMany(targetEntity: Theme::class)]
        #[ORM\JoinTable(name: 'subscription_plan_theme')]
        private ?Collection $themes = null,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->subscriptionModules = $this->subscriptionModules ?? new ArrayCollection();
        $this->planQuotas = $this->planQuotas ?? new ArrayCollection();
        $this->themes = $this->themes ?? new ArrayCollection();
        parent::__construct();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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

    public function getDurationMonths(): int
    {
        return $this->durationMonths;
    }

    public function setDurationMonths(int $durationMonths): void
    {
        $this->durationMonths = $durationMonths;
        $this->touch();
    }

    public function getPriceTnd(): int
    {
        return $this->priceTnd;
    }

    public function setPriceTnd(int $priceTnd): void
    {
        $this->priceTnd = max(0, $priceTnd);
        $this->touch();
    }

    public function getRenewalPriceTnd(): ?int
    {
        return $this->renewalPriceTnd;
    }

    public function setRenewalPriceTnd(?int $renewalPriceTnd): void
    {
        $this->renewalPriceTnd = null === $renewalPriceTnd ? null : max(0, $renewalPriceTnd);
        $this->touch();
    }

    /** Prix appliqué au renouvellement : renewalPriceTnd si défini, sinon priceTnd. */
    public function getEffectiveRenewalPriceTnd(): int
    {
        return $this->renewalPriceTnd ?? $this->priceTnd;
    }

    public function isFree(): bool
    {
        return $this->isFree;
    }

    public function setIsFree(bool $isFree): void
    {
        $this->isFree = $isFree;
        $this->touch();
    }

    public function isVisible(): bool
    {
        return $this->isVisible;
    }

    public function setIsVisible(bool $isVisible): void
    {
        $this->isVisible = $isVisible;
        $this->touch();
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
        $this->touch();
    }

    public function getModules(): ?array
    {
        return $this->modules;
    }

    public function setModules(?array $modules): void
    {
        $this->modules = $modules;
        $this->touch();
    }

    public function getChatbotModel(): ?string
    {
        return $this->chatbotModel;
    }

    public function setChatbotModel(?string $chatbotModel): void
    {
        $this->chatbotModel = $chatbotModel;
        $this->touch();
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
        $this->touch();
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): void
    {
        $this->displayOrder = $displayOrder;
        $this->touch();
    }

    /** @return Collection<int, PlanQuota> */
    public function getPlanQuotas(): Collection
    {
        return $this->planQuotas;
    }

    public function addPlanQuota(PlanQuota $planQuota): void
    {
        if (!$this->planQuotas->contains($planQuota)) {
            $this->planQuotas->add($planQuota);
        }
    }

    public function removePlanQuota(PlanQuota $planQuota): void
    {
        $this->planQuotas->removeElement($planQuota);
    }

    /** @return Collection<int, Theme> */
    public function getThemes(): Collection
    {
        return $this->themes;
    }

    public function addTheme(Theme $theme): void
    {
        if (!$this->themes->contains($theme)) {
            $this->themes->add($theme);
        }
        $this->touch();
    }

    public function removeTheme(Theme $theme): void
    {
        $this->themes->removeElement($theme);
        $this->touch();
    }

    public function setThemes(Collection $themes): void
    {
        $this->themes = $themes;
        $this->touch();
    }

    /** @return Collection<int, SubscriptionModule> */
    public function getSubscriptionModules(): Collection
    {
        return $this->subscriptionModules;
    }

    public function addSubscriptionModule(SubscriptionModule $subscriptionModule): void
    {
        if (!$this->subscriptionModules->contains($subscriptionModule)) {
            $this->subscriptionModules->add($subscriptionModule);
        }
    }

    public function removeSubscriptionModule(SubscriptionModule $subscriptionModule): void
    {
        $this->subscriptionModules->removeElement($subscriptionModule);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
