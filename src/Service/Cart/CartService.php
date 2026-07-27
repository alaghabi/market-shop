<?php

namespace App\Service\Cart;

use App\Dto\Cart\CartCheckoutInput;
use App\Dto\Cart\CartCheckoutOutput;
use App\Dto\Cart\CartItemOutput;
use App\Dto\Cart\CartOutput;
use App\Entity\Boutique;
use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Customer;
use App\Entity\Country;
use App\Entity\Governorate;
use App\Entity\Locality;
use App\Entity\Order;
use App\Entity\PaymentMethod;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\OrderChannel;
use App\Enum\OrderStatus;
use App\Repository\PaymentMethodRepository;
use App\Repository\ShopPaymentMethodRepository;
use App\Repository\BoutiqueRepository;
use App\Repository\CartItemRepository;
use App\Repository\CartRepository;
use App\Repository\CountryRepository;
use App\Repository\CustomerRepository;
use App\Repository\GovernorateRepository;
use App\Repository\LocalityRepository;
use App\Repository\ProductRepository;
use App\Service\AppConfigService;
use App\Service\Loyalty\Dto\LoyaltyCalculationResult;
use App\Service\Loyalty\Dto\LoyaltyRedemptionRequest;
use App\Service\Loyalty\LoyaltyEngine;
use App\Service\Subscription\SubscriptionManager;
use App\Service\Webhook\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final readonly class CartService
{
    public function __construct(
        private BoutiqueRepository $boutiques,
        private ProductRepository $products,
        private CartRepository $carts,
        private CartItemRepository $cartItems,
        private CustomerRepository $customers,
        private CountryRepository $countries,
        private GovernorateRepository $governorates,
        private LocalityRepository $localities,
        private PaymentMethodRepository $paymentMethods,
        private ShopPaymentMethodRepository $shopPaymentMethods,
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
        private AppConfigService $appConfig,
        private WebhookService $webhookService,
        private LoyaltyEngine $loyaltyEngine,
        private SubscriptionManager $subscriptionManager,
    ) {
    }

    public function currentCart(string $boutiqueId): CartOutput
    {
        return $this->toOutput($this->getOrCreateCart($boutiqueId));
    }

    public function addItem(string $boutiqueId, string $productId, int $quantity, ?string $variantId = null): CartOutput
    {
        if ($quantity < 1) {
            throw new BadRequestHttpException('Quantity must be greater than zero.');
        }

        $cart = $this->getOrCreateCart($boutiqueId);
        $product = $this->products->find($productId);
        if (!$product || (string) $product->getBoutique()->getId() !== (string) $cart->getBoutique()->getId() || !$product->isActive()) {
            throw new NotFoundHttpException('Product not found.');
        }

        $variant = $this->findVariant($product, $variantId);
        $cart->addItem($product, $quantity, $variant);
        $this->em->flush();

        return $this->toOutput($cart);
    }

    public function updateItem(string $boutiqueId, string $itemId, int $quantity, ?string $variantId = null): CartOutput
    {
        if ($quantity < 1) {
            throw new BadRequestHttpException('Quantity must be greater than zero.');
        }

        $cart = $this->getOrCreateCart($boutiqueId);
        $item = $this->findCartItem($cart, $itemId);
        $product = $item->getProduct();
        if (!$product instanceof Product) {
            throw new NotFoundHttpException('Product not found.');
        }

        $variant = $this->findVariant($product, $variantId);
        $currentVariantId = (string) $item->getVariant()?->getId();
        $nextVariantId = (string) $variant?->getId();
        if ($currentVariantId !== $nextVariantId) {
            $existing = $cart->findItemForProduct($product, $variant);
            if ($existing instanceof CartItem && $existing !== $item) {
                $existing->changeQuantity($existing->getQuantity() + $quantity);
                $cart->removeItem($item);
                $this->em->remove($item);
            } else {
                $item->changeVariant($variant, $variant?->getSellingPrice() ?? $product->getSellingPrice());
                $item->changeQuantity($quantity);
            }
        } else {
            $item->changeQuantity($quantity);
        }
        $cart->touch();
        $this->em->flush();

        return $this->toOutput($cart);
    }

    public function removeItem(string $boutiqueId, string $itemId): CartOutput
    {
        $cart = $this->getOrCreateCart($boutiqueId);
        $item = $this->findCartItem($cart, $itemId);
        $cart->removeItem($item);
        $this->em->remove($item);
        $this->em->flush();

        return $this->toOutput($cart);
    }

    public function checkout(string $boutiqueId, CartCheckoutInput $input): CartCheckoutOutput
    {
        $cart = $this->getOrCreateCart($boutiqueId);
        if ($cart->getItems()->isEmpty()) {
            throw new BadRequestHttpException('Cart is empty.');
        }

        $address = $this->resolveAddressReference($input);
        $customer = $this->resolveCustomer($cart->getBoutique(), $input, $address);
        $cart->setCustomer($customer);
        $paymentMethod = $this->resolvePaymentMethod($cart->getBoutique(), $input->paymentMethodCode, $cart->getTotalCents());

        $subtotalCents = $cart->getTotalCents();
        $loyaltyResult = $this->calculateLoyaltyRedemption($cart->getBoutique(), $customer, $input, $subtotalCents);
        $loyaltyDiscountCents = $loyaltyResult?->success ? $loyaltyResult->discountCents : 0;

        $order = new Order(
            $cart->getBoutique(),
            $customer,
            OrderChannel::Online,
            OrderStatus::Pending,
            $subtotalCents,
            $loyaltyDiscountCents,
            max(0, $subtotalCents - $loyaltyDiscountCents),
            $cart->getCurrency(),
        );
        $order->setCustomerSnapshot(
            $this->customerName($input),
            strtolower(trim((string) $input->customerEmail)) ?: null,
            $this->nullableTrim($input->phone),
            $address['shippingAddress'],
            $address['shippingCity'],
            $address['shippingPostalCode'],
            $address['shippingCountry'],
            $address['shippingCountryId'],
            $address['shippingGovernorate'],
            $address['shippingGovernorateId'],
            $address['shippingLocality'],
            $address['shippingLocalityId'],
        );
        $order->setPaymentMethodCode($paymentMethod?->getCode());

        foreach ($cart->getItems() as $item) {
            $product = $item->getProduct();
            $variant = $item->getVariant();
            $productName = $product?->getName() ?? 'Produit supprimé';
            if ($variant instanceof ProductVariant && !$variant->getAttributes()->isEmpty()) {
                $attributes = array_map(
                    static fn ($attribute): string => sprintf('%s: %s', $attribute->getAttributeName(), $attribute->getAttributeValue()),
                    $variant->getAttributes()->toArray(),
                );
                $productName .= ' ('.implode(', ', $attributes).')';
            }
            $order->addItem(
                $product,
                $productName,
                $variant?->getSku() ?? $product?->getSku() ?? '',
                $item->getQuantity(),
                $item->getUnitPriceCents(),
                $variant,
            );
        }

        $cart->markOrdered();
        $this->em->persist($order);
        $this->em->flush();
        $this->clearCookieFor($cart->getBoutique());

        if ($loyaltyResult?->success && $customer instanceof Customer) {
            $this->loyaltyEngine->confirmRedemption($order, $customer, $loyaltyResult);
        }

        // Dispatch order.created webhook
        $this->webhookService->dispatchEvent('order.created', [
            'id' => (string) $order->getId(),
            'status' => $order->getStatus()->value,
            'payment_status' => $order->getPaymentStatus()->value,
            'total_cents' => $order->getTotalCents(),
            'currency' => $order->getCurrency(),
            'channel' => $order->getChannel()->value,
            'customer_name' => $order->getCustomerName(),
            'customer_email' => $order->getCustomerEmail(),
            'items_count' => $order->getItems()->count(),
        ], (string) $order->getBoutique()->getId());

        $output = new CartCheckoutOutput();
        $output->orderId = (string) $order->getId();
        $output->cartId = (string) $cart->getId();
        $output->status = OrderStatus::Pending->value;
        $output->paymentStatus = $order->getPaymentStatus()->value;
        $output->paymentMethodCode = $order->getPaymentMethodCode();
        $output->totalCents = $order->getTotalCents();
        $output->currency = $order->getCurrency();
        $output->customerName = $order->getCustomerName();
        $output->subtotalCents = $order->getSubtotalCents();
        $output->loyaltyDiscountCents = $loyaltyResult?->success ? $loyaltyResult->discountCents : 0;
        $output->loyaltyPointsUsed = $loyaltyResult?->success ? $loyaltyResult->pointsUsed : 0;

        return $output;
    }

    /**
     * Dry-run loyalty calculation before order creation. Never blocks
     * checkout: any failure (no account, module disabled, guest customer...)
     * simply results in no loyalty discount being applied.
     */
    private function calculateLoyaltyRedemption(Boutique $boutique, ?Customer $customer, CartCheckoutInput $input, int $subtotalCents): ?LoyaltyCalculationResult
    {
        if (!$customer instanceof Customer) {
            return null;
        }

        $request = new LoyaltyRedemptionRequest(
            useAllPoints: $input->useLoyaltyPoints,
            pointsToUse: $input->loyaltyPointsToUse,
            rewardId: $input->loyaltyRewardId,
        );

        if ($request->isEmpty()) {
            return null;
        }

        return $this->loyaltyEngine->calculateRedemption($boutique, $customer, $request, $subtotalCents);
    }

    private function getOrCreateCart(string $boutiqueId): Cart
    {
        $boutique = $this->findBoutique($boutiqueId);
        $request = $this->requestStack->getCurrentRequest();
        $cookieName = $this->cookieName($boutique);
        $cookieCartId = $request?->cookies->get($cookieName);

        if (is_string($cookieCartId) && Uuid::isValid($cookieCartId)) {
            $cart = $this->carts->findActiveForBoutique($cookieCartId, $boutique);
            if ($cart instanceof Cart) {
                $this->writeCookieFor($cart);

                return $cart;
            }
        }

        $cart = new Cart($boutique, bin2hex(random_bytes(32)), null, currency: 'TND');
        $this->em->persist($cart);
        $this->em->flush();
        $this->writeCookieFor($cart);

        return $cart;
    }

    private function findBoutique(string $boutiqueId): Boutique
    {
        $boutique = $this->boutiques->find($boutiqueId);
        if (!$boutique instanceof Boutique) {
            throw new NotFoundHttpException('Boutique not found.');
        }

        return $boutique;
    }

    private function findCartItem(Cart $cart, string $itemId): CartItem
    {
        $item = $this->cartItems->find($itemId);
        if (!$item instanceof CartItem || (string) $item->getCart()->getId() !== (string) $cart->getId()) {
            throw new NotFoundHttpException('Cart item not found.');
        }

        return $item;
    }

    private function findVariant(Product $product, ?string $variantId): ?ProductVariant
    {
        if (null === $variantId || '' === trim($variantId)) {
            return null;
        }

        foreach ($product->getVariants() as $variant) {
            if ((string) $variant->getId() !== $variantId) {
                continue;
            }

            if (!$variant->isActive()) {
                throw new NotFoundHttpException('Product variant not found.');
            }

            return $variant;
        }

        throw new NotFoundHttpException('Product variant not found.');
    }

    /**
     * @param array{shippingAddress:?string,shippingCity:?string,shippingPostalCode:?string,shippingCountry:?string,shippingCountryId:?string,shippingGovernorate:?string,shippingGovernorateId:?string,shippingLocality:?string,shippingLocalityId:?string} $address
     */
    private function resolveCustomer(Boutique $boutique, CartCheckoutInput $input, array $address): ?Customer
    {
        $email = strtolower(trim((string) $input->customerEmail));
        if ('' === $email) {
            return null;
        }

        $customer = $this->customers->findOneBy(['boutique' => $boutique, 'email' => $email]);
        if ($customer instanceof Customer) {
            $customer->setFirstName($this->nullableTrim($input->firstName));
            $customer->setLastName($this->nullableTrim($input->lastName));
            $customer->setPhone($this->nullableTrim($input->phone));
            $customer->setAddressSnapshot(
                $address['shippingAddress'],
                $address['shippingCity'],
                $address['shippingPostalCode'],
                $address['shippingCountry'],
                $address['shippingCountryId'],
                $address['shippingGovernorate'],
                $address['shippingGovernorateId'],
                $address['shippingLocality'],
                $address['shippingLocalityId'],
            );

            return $customer;
        }

        if (!$this->subscriptionManager->canCreateCustomer($boutique)) {
            throw new ConflictHttpException('Le quota clients de cette boutique est atteint ou son abonnement est inactif.');
        }

        $customer = new Customer(
            $boutique,
            $email,
            $this->nullableTrim($input->firstName),
            $this->nullableTrim($input->lastName),
            $this->nullableTrim($input->phone),
        );
        $customer->setAddressSnapshot(
            $address['shippingAddress'],
            $address['shippingCity'],
            $address['shippingPostalCode'],
            $address['shippingCountry'],
            $address['shippingCountryId'],
            $address['shippingGovernorate'],
            $address['shippingGovernorateId'],
            $address['shippingLocality'],
            $address['shippingLocalityId'],
        );
        $this->em->persist($customer);

        return $customer;
    }

    /**
     * @return array{shippingAddress:?string,shippingCity:?string,shippingPostalCode:?string,shippingCountry:?string,shippingCountryId:?string,shippingGovernorate:?string,shippingGovernorateId:?string,shippingLocality:?string,shippingLocalityId:?string}
     */
    private function resolveAddressReference(CartCheckoutInput $input): array
    {
        $countryId = $this->nullableTrim($input->shippingCountryId);
        $governorateId = $this->nullableTrim($input->shippingGovernorateId);
        $localityId = $this->nullableTrim($input->shippingLocalityId);

        $country = null;
        if (null !== $countryId) {
            $country = $this->countries->find($countryId);
            if (!$country instanceof Country || !$country->isActive()) {
                throw new BadRequestHttpException('Invalid country.');
            }
        }

        $governorate = null;
        if (null !== $governorateId) {
            $governorate = $this->governorates->find($governorateId);
            if (!$governorate instanceof Governorate || !$governorate->isActive()) {
                throw new BadRequestHttpException('Invalid governorate.');
            }
        }

        $locality = null;
        if (null !== $localityId) {
            $locality = $this->localities->find($localityId);
            if (!$locality instanceof Locality || !$locality->isActive()) {
                throw new BadRequestHttpException('Invalid locality.');
            }
        }

        if ($governorate instanceof Governorate && $country instanceof Country && (string) $governorate->getCountry()->getId() !== (string) $country->getId()) {
            throw new BadRequestHttpException('Governorate does not belong to country.');
        }

        if ($locality instanceof Locality && $governorate instanceof Governorate && (string) $locality->getGovernorate()->getId() !== (string) $governorate->getId()) {
            throw new BadRequestHttpException('Locality does not belong to governorate.');
        }

        if ($locality instanceof Locality && $country instanceof Country && (string) $locality->getGovernorate()->getCountry()->getId() !== (string) $country->getId()) {
            throw new BadRequestHttpException('Locality does not belong to country.');
        }

        return [
            'shippingAddress' => $this->nullableTrim($input->shippingAddress),
            'shippingCity' => $locality?->getName() ?? $this->nullableTrim($input->shippingCity),
            'shippingPostalCode' => $locality?->getPostalCode() ?? $this->nullableTrim($input->shippingPostalCode),
            'shippingCountry' => $country?->getName() ?? $this->nullableTrim($input->shippingCountry),
            'shippingCountryId' => $country ? (string) $country->getId() : $countryId,
            'shippingGovernorate' => $governorate?->getName() ?? $this->nullableTrim($input->shippingGovernorate),
            'shippingGovernorateId' => $governorate ? (string) $governorate->getId() : $governorateId,
            'shippingLocality' => $locality?->getName() ?? $this->nullableTrim($input->shippingLocality),
            'shippingLocalityId' => $locality ? (string) $locality->getId() : $localityId,
        ];
    }

    private function resolvePaymentMethod(Boutique $boutique, ?string $paymentMethodCode, int $cartTotalCents): ?PaymentMethod
    {
        $activeMethods = $this->shopPaymentMethods->findActiveForBoutique($boutique);
        if ([] === $activeMethods) {
            return null;
        }

        if (!$this->appConfig->isModuleEnabled('paiements')) {
            throw new BadRequestHttpException('Payments are disabled globally.');
        }

        $paymentsConfig = $this->appConfig->section('payments');

        $paymentMethodCode = strtoupper(trim((string) $paymentMethodCode));
        if ('' === $paymentMethodCode) {
            throw new BadRequestHttpException('Payment method is required.');
        }

        $paymentMethod = $this->paymentMethods->findOneByCode($paymentMethodCode);
        if (!$paymentMethod instanceof PaymentMethod || !$this->shopPaymentMethods->hasActiveCodeForBoutique($boutique, $paymentMethodCode)) {
            throw new BadRequestHttpException('Payment method is not available for this boutique.');
        }

        $visibleMethods = is_array($paymentsConfig['visible_methods'] ?? null) ? array_map('strtoupper', array_map('strval', $paymentsConfig['visible_methods'])) : [];
        if ([] !== $visibleMethods && !in_array($paymentMethodCode, $visibleMethods, true)) {
            throw new BadRequestHttpException('Payment method is disabled globally.');
        }

        if ('CASH_ON_DELIVERY' === $paymentMethodCode && !($paymentsConfig['cash_on_delivery_enabled'] ?? true)) {
            throw new BadRequestHttpException('Cash on delivery is disabled globally.');
        }
        if ('BANK_TRANSFER' === $paymentMethodCode && !($paymentsConfig['bank_transfer_enabled'] ?? true)) {
            throw new BadRequestHttpException('Bank transfer is disabled globally.');
        }
        if (!in_array($paymentMethodCode, ['CASH_ON_DELIVERY', 'BANK_TRANSFER'], true) && !($paymentsConfig['online_payment_enabled'] ?? true)) {
            throw new BadRequestHttpException('Online payments are disabled globally.');
        }

        $shopMethod = null;
        foreach ($activeMethods as $candidate) {
            if ($candidate->getPaymentMethod()->getCode() === $paymentMethodCode) {
                $shopMethod = $candidate;
                break;
            }
        }
        if (null === $shopMethod) {
            throw new BadRequestHttpException('Payment method is not available for this boutique.');
        }
        if (null !== $shopMethod->getMinimumAmountCents() && $cartTotalCents < $shopMethod->getMinimumAmountCents()) {
            throw new BadRequestHttpException('Cart total is below the minimum amount for this payment method.');
        }
        if (null !== $shopMethod->getMaximumAmountCents() && $cartTotalCents > $shopMethod->getMaximumAmountCents()) {
            throw new BadRequestHttpException('Cart total exceeds the maximum amount for this payment method.');
        }

        return $paymentMethod;
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    private function customerName(CartCheckoutInput $input): ?string
    {
        $name = trim(sprintf('%s %s', (string) $input->firstName, (string) $input->lastName));

        return '' === $name ? null : $name;
    }

    private function writeCookieFor(Cart $cart): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $request->attributes->set(CartCookieSubscriber::COOKIE_NAME_ATTRIBUTE, $this->cookieName($cart->getBoutique()));
        $request->attributes->set(CartCookieSubscriber::COOKIE_VALUE_ATTRIBUTE, (string) $cart->getId());
    }

    private function clearCookieFor(Boutique $boutique): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $request->attributes->set(CartCookieSubscriber::COOKIE_NAME_ATTRIBUTE, $this->cookieName($boutique));
        $request->attributes->set(CartCookieSubscriber::COOKIE_CLEAR_ATTRIBUTE, true);
    }

    private function cookieName(Boutique $boutique): string
    {
        return sprintf('market_shop_cart_%s', $boutique->getSlug());
    }

    public function toOutput(Cart $cart): CartOutput
    {
        $output = new CartOutput();
        $output->id = (string) $cart->getId();
        $output->boutiqueId = (string) $cart->getBoutique()->getId();
        $output->status = $cart->getStatus()->value;
        $output->currency = $cart->getCurrency();
        $output->itemsCount = $cart->getItems()->count();
        $output->totalCents = $cart->getTotalCents();
        $output->customerEmail = $cart->getCustomer()?->getEmail();
        $output->firstName = $cart->getCustomer()?->getFirstName();
        $output->lastName = $cart->getCustomer()?->getLastName();
        $output->phone = $cart->getCustomer()?->getPhone();
        $output->address = $cart->getCustomer()?->getAddress();
        $output->city = $cart->getCustomer()?->getCity();
        $output->postalCode = $cart->getCustomer()?->getPostalCode();
        $output->country = $cart->getCustomer()?->getCountry();
        $output->countryId = $cart->getCustomer()?->getCountryId();
        $output->governorate = $cart->getCustomer()?->getGovernorate();
        $output->governorateId = $cart->getCustomer()?->getGovernorateId();
        $output->locality = $cart->getCustomer()?->getLocality();
        $output->localityId = $cart->getCustomer()?->getLocalityId();
        $output->items = array_map([$this, 'toItemOutput'], $cart->getItems()->toArray());
        $output->createdAt = $cart->getCreatedAt();
        $output->updatedAt = $cart->getUpdatedAt();
        $output->expiresAt = $cart->getExpiresAt();

        return $output;
    }

    private function toItemOutput(CartItem $item): CartItemOutput
    {
        $product = $item->getProduct();
        $output = new CartItemOutput();
        $output->id = (string) $item->getId();
        $output->productId = $product ? (string) $product->getId() : null;
        $output->productName = $product?->getName();
        $output->sku = $item->getVariant()?->getSku() ?? $product?->getSku();
        $output->variantId = $item->getVariant() ? (string) $item->getVariant()->getId() : null;
        $output->variantSku = $item->getVariant()?->getSku();
        $output->variantAttributes = $item->getVariant()
            ? array_map(static fn ($attribute): array => [
                'name' => $attribute->getAttributeName(),
                'value' => $attribute->getAttributeValue(),
            ], $item->getVariant()->getAttributes()->toArray())
            : [];
        $output->availableVariants = $product
            ? array_values(array_map(static fn (ProductVariant $variant): array => [
                'id' => (string) $variant->getId(),
                'sku' => $variant->getSku(),
                'sellingPrice' => $variant->getSellingPrice(),
                'quantity' => $variant->getQuantity(),
                'attributes' => array_map(static fn ($attribute): array => [
                    'name' => $attribute->getAttributeName(),
                    'value' => $attribute->getAttributeValue(),
                ], $variant->getAttributes()->toArray()),
            ], $product->getVariants()->filter(static fn (ProductVariant $variant): bool => $variant->isActive())->toArray()))
            : [];
        $output->quantity = $item->getQuantity();
        $output->unitPriceCents = $item->getUnitPriceCents();
        $output->totalCents = $item->getTotalCents();

        return $output;
    }
}
