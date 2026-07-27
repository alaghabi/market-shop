<?php

namespace App\ApiResource\Storefront;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Dto\Storefront\MostOrderedProductOutput;
use App\State\Storefront\MostOrderedProductProvider;

#[ApiResource(
    shortName: 'MostOrderedProduct',
    operations: [
        new Get(
            name: 'public_most_ordered_product',
            uriTemplate: '/public/most-ordered-product',
            security: "is_granted('PUBLIC_ACCESS')",
            output: MostOrderedProductOutput::class,
            provider: MostOrderedProductProvider::class,
        ),
    ],
)]
final class MostOrderedProductResource
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
