<?php

namespace App\Dto\AccountSubscription;

final class AccountSubscriptionOutput
{
    public bool $isActive = false;
    public ?string $id = null;
    public ?string $planId = null;
    public ?string $planName = null;
    public ?string $planDescription = null;
    public int $planDurationMonths = 0;
    /** Prix principal du plan (souscription / 1ère période), millimes. */
    public int $planPriceTnd = 0;
    /** Prix de renouvellement stocké sur le plan (null = fallback sur planPriceTnd). */
    public ?int $planRenewalPriceTnd = null;
    /** Prix plan effectif au renouvellement (planRenewalPriceTnd ?? planPriceTnd). */
    public int $effectivePlanRenewalPriceTnd = 0;
    /** Somme des prix des extensions actives. */
    public int $extensionsRenewalPriceTnd = 0;
    /** Total renouvellement = effectivePlanRenewalPriceTnd + extensionsRenewalPriceTnd. */
    public int $renewalPriceTnd = 0;
    public string $currency = 'TND';
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?int $daysRemaining = null;
    public ?string $status = null;

    /** @var list<array{id: string, code: string, name: string, targetCode: string|null, value: int|null, priceTnd: int, durationMonths: int|null, expiresAt: string|null, isActive: bool}> */
    public array $extensions = [];

    public ?int $maxBoutiques = null;
    /** Boutiques publiées du owner — même métrique que le contrôle de publication. */
    public int $publishedBoutiques = 0;
    public bool $isUnlimited = false;
}
