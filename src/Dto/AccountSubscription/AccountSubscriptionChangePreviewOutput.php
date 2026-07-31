<?php

namespace App\Dto\AccountSubscription;

final class AccountSubscriptionChangePreviewOutput
{
    public ?string $currentPlanId = null;
    public ?string $currentPlanName = null;
    public string $newPlanId;
    public string $newPlanName;
    /** Prix principal (1ère souscription / changement) du nouveau plan. */
    public int $newPlanPriceTnd = 0;
    /** Prix de renouvellement stocké (null = fallback newPlanPriceTnd). */
    public ?int $newPlanRenewalPriceTnd = null;
    /** Prix plan effectif au prochain renouvellement. */
    public int $effectivePlanRenewalPriceTnd = 0;
    /** Somme des extensions actives reprises. */
    public int $extensionsRenewalPriceTnd = 0;
    /** Total renouvellement estimé (plan effectif + extensions). */
    public int $renewalPriceTnd = 0;
    public string $currency = 'TND';
    public int $durationMonths = 1;
    public bool $isRenewal = false;
    public ?string $projectedEndDate = null;
    public ?int $newMaxBoutiques = null;
    public bool $newIsUnlimited = false;
    public int $publishedBoutiques = 0;
    public bool $wouldExceedQuota = false;
}
