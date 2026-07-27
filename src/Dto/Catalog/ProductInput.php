<?php

namespace App\Dto\Catalog;

use Symfony\Component\Validator\Constraints as Assert;

final class ProductInput
{
    public ?string $boutiqueId = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    public string $name;

    #[Assert\Length(max: 200)]
    public ?string $slug = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    public ?string $sku = null;

    public ?string $barcode = null;

    public ?string $shortDescription = null;

    public ?string $description = null;

    public string $status = 'DRAFT';

    #[Assert\PositiveOrZero]
    public int $costPrice = 0;

    #[Assert\PositiveOrZero]
    public int $sellingPrice = 0;

    #[Assert\PositiveOrZero]
    public int $comparePrice = 0;

    #[Assert\PositiveOrZero]
    public int $taxRate = 0;

    #[Assert\PositiveOrZero]
    public int $weight = 0;

    #[Assert\PositiveOrZero]
    public int $length = 0;

    #[Assert\PositiveOrZero]
    public int $width = 0;

    #[Assert\PositiveOrZero]
    public int $height = 0;

    public bool $manageStock = true;

    #[Assert\PositiveOrZero]
    public int $stockQuantity = 0;

    #[Assert\PositiveOrZero]
    public int $lowStockThreshold = 5;

    public bool $isFeatured = false;

    public bool $isBestSeller = false;

    public bool $isNew = false;

    public bool $isVirtual = false;

    public ?string $metaTitle = null;

    public ?string $metaDescription = null;

    public ?string $metaKeywords = null;

    public ?string $ogTitle = null;

    public ?string $ogDescription = null;

    public ?string $ogImage = null;

    public ?string $publishedAt = null;

    public ?string $brandId = null;

    public string $currency = 'TND';

    public ?string $categoryId = null;

    /** @var list<string> */
    public array $categoryIds = [];

    /** @var list<string> */
    public array $images = [];

    public ?string $defaultImageUrl = null;

    /** @var array<string, string|list<string>> */
    public array $filterValues = [];

    /** @var list<array{sku?: string, barcode?: string, sellingPrice?: int, comparePrice?: int, quantity?: int, image?: string, isDefault?: bool, attributes?: array<array{name: string, value: string}>}> */
    public array $variants = [];

    /** @var list<array{name: string, value: string}> */
    public array $properties = [];
}
