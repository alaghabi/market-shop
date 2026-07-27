<?php

namespace App\Dto\Cart;

final class CartItemOutput
{
    public string $id;
    public ?string $productId;
    public ?string $productName;
    public ?string $sku;
    public ?string $variantId = null;
    public ?string $variantSku = null;
    /** @var list<array{name: string, value: string}> */
    public array $variantAttributes = [];
    /** @var list<array{id: string, sku: ?string, sellingPrice: int, quantity: int, attributes: list<array{name: string, value: string}>}> */
    public array $availableVariants = [];
    public int $quantity;
    public int $unitPriceCents;
    public int $totalCents;
}
