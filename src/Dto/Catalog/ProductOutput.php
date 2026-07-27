<?php

namespace App\Dto\Catalog;

final class ProductOutput
{
    public string $id;
    public string $boutiqueId;
    public string $name;
    public string $slug;
    public string $sku;
    public ?string $barcode;
    public ?string $shortDescription;
    public ?string $description;
    public string $status;
    public int $costPrice;
    public int $sellingPrice;
    public int $comparePrice;
    public int $taxRate;
    public int $weight;
    public int $length;
    public int $width;
    public int $height;
    public bool $manageStock;
    public int $stockQuantity;
    public int $lowStockThreshold;
    public bool $isFeatured;
    public bool $isBestSeller;
    public bool $isNew;
    public bool $isVirtual;
    public ?string $metaTitle;
    public ?string $metaDescription;
    public ?string $metaKeywords;
    public ?string $ogTitle;
    public ?string $ogDescription;
    public ?string $ogImage;
    public ?string $publishedAt;
    public ?string $brandId;
    public ?string $brandName;
    public string $currency;
    public ?string $categoryId;
    public ?string $categoryName;
    public ?string $categorySlug;
    /** @var list<string> */
    public array $categoryIds = [];
    /** @var list<array{url: string, smallUrl: ?string, largeUrl: ?string, alt: ?string}> */
    public array $images = [];
    /** @var list<array{type: string, filePath: string, position: int, altText: ?string, isPrimary: bool}> */
    public array $media = [];
    /** @var list<array{id: string, sku: ?string, sellingPrice: int, comparePrice: int, quantity: int, image: ?string, isDefault: bool, isActive: bool, attributes: array<array{name: string, value: string}>}> */
    public array $variants = [];
    /** @var list<array{name: string, value: string}> */
    public array $properties = [];
    /** @var array<string, array{filterId: string, filterName: string, filterSlug: string, value: string}> */
    public array $filterValues = [];
    public int $ordersCount = 0;
    public int $viewsCount = 0;
    public int $reviewsCount = 0;
    public ?float $rating = null;
    public int $favoritesCount = 0;
    public float $revenue = 0.0;
    public \DateTimeImmutable $createdAt;
    public ?\DateTimeImmutable $updatedAt;
}
