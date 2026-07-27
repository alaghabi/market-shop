<?php

namespace App\State\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Boutique;
use App\Dto\Cart\CartCheckoutInput;
use App\Dto\Cart\CartCheckoutOutput;
use App\Dto\Cart\CartItemInput;
use App\Dto\Cart\CartItemQuantityInput;
use App\Dto\Cart\CartOutput;
use App\Service\Cart\CartService;
use App\Service\Security\PublicApiRateLimiter;
use Symfony\Component\HttpFoundation\Request;

/** @implements ProcessorInterface<object, CartOutput|CartCheckoutOutput> */
final readonly class CartProcessor implements ProcessorInterface
{
    public function __construct(
        private CartService $carts,
        private PublicApiRateLimiter $rateLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CartOutput|CartCheckoutOutput
    {
        $boutiqueId = (string) ($uriVariables['boutiqueId'] ?? '');
        if ('' === $boutiqueId) {
            $request = $context['request'] ?? null;
            $boutique = $request instanceof Request ? $request->attributes->get('_boutique') : null;
            if ($boutique instanceof Boutique) {
                $boutiqueId = (string) $boutique->getId();
            }
        }
        $operationName = $operation->getName() ?? '';

        if ('cart_add_item' === $operationName && $data instanceof CartItemInput) {
            $this->rateLimiter->consumeCart('add-item');

            return $this->carts->addItem($boutiqueId, $data->productId, $data->quantity, $data->variantId);
        }

        if ('cart_update_item' === $operationName && $data instanceof CartItemQuantityInput) {
            $this->rateLimiter->consumeCart('update-item');

            return $this->carts->updateItem($boutiqueId, (string) ($uriVariables['itemId'] ?? ''), $data->quantity, $data->variantId);
        }

        if ('cart_remove_item' === $operationName) {
            $this->rateLimiter->consumeCart('remove-item');

            return $this->carts->removeItem($boutiqueId, (string) ($uriVariables['itemId'] ?? ''));
        }

        if ('cart_checkout' === $operationName && $data instanceof CartCheckoutInput) {
            $this->rateLimiter->consumeCheckout();

            return $this->carts->checkout($boutiqueId, $data);
        }

        throw new \InvalidArgumentException('Unsupported cart operation.');
    }
}
