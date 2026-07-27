<?php

namespace App\Dto\Storefront;

final class MostOrderedProductOutput
{
    public ?string $boutiqueSlug = null;
    public ?string $id = null;
    public ?string $name = null;
    public ?string $slug = null;
    public ?string $shortDescription = null;
    public int $priceCents = 0;
    public int $comparePriceCents = 0;
    public string $currency = 'TND';
    public ?string $imageUrl = null;
    public int $quantitySold = 0;
    public ?string $periodStart = null;
    public ?string $periodEnd = null;
}
