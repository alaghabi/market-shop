<?php

namespace App\Dto\SubscriptionPlan;

use Symfony\Component\Validator\Constraints as Assert;

final class SubscriptionPlanInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    public string $name;

    public ?string $description = null;

    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $durationMonths;

    public int $priceTnd = 0;

    /** Null = renouvellement au prix principal (priceTnd). */
    #[Assert\PositiveOrZero]
    public ?int $renewalPriceTnd = null;

    public bool $isFree = false;

    public bool $isVisible = true;

    public bool $isActive = true;

    /** @var list<string>|null */
    public ?array $modules = null;

    #[Assert\Length(max: 8)]
    public string $currency = 'TND';

    public int $displayOrder = 0;

    /** @var list<string>|null theme codes included in this plan */
    public ?array $themeCodes = null;

    /** @var list<array{quotaCode: string, limitValue: int|null}>|null */
    public ?array $quotas = null;
}
