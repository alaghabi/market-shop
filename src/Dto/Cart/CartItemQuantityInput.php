<?php

namespace App\Dto\Cart;

use Symfony\Component\Validator\Constraints as Assert;

final class CartItemQuantityInput
{
    #[Assert\Range(min: 1, max: 999)]
    public int $quantity = 1;

    public ?string $variantId = null;
}
