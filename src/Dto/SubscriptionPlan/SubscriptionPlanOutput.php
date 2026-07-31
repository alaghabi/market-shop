<?php

namespace App\Dto\SubscriptionPlan;

final class SubscriptionPlanOutput
{
    public ?string $id = null;
    public string $name;
    public ?string $description = null;
    public int $durationMonths;
    public int $priceTnd = 0;
    /** Null = renouvellement au prix principal. */
    public ?int $renewalPriceTnd = null;
    /** Prix effectif de renouvellement (renewalPriceTnd ?? priceTnd). */
    public int $effectiveRenewalPriceTnd = 0;
    public bool $isFree = false;
    public bool $isVisible = true;
    public bool $isActive = true;
    /** @var list<string>|null */
    public ?array $modules = null;
    public string $currency = 'TND';
    public int $displayOrder = 0;
    /** @var list<array{id: string, code: string, name: string}> */
    public array $themes = [];
    /** @var list<array{quotaCode: string, quotaName: string, limitValue: int|null}> */
    public array $quotas = [];
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
}
