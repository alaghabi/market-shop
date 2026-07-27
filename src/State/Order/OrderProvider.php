<?php

namespace App\State\Order;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Order\OrderOutput;
use App\Entity\Boutique;
use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Repository\BoutiqueRepository;
use App\Repository\OrderRepository;
use App\Security\BoutiqueContext;
use App\State\Common\BoutiqueAwareProviderTrait;
use Symfony\Component\HttpFoundation\Request;

/** @implements ProviderInterface<OrderOutput> */
final readonly class OrderProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private OrderRepository $orders,
        private BoutiqueRepository $boutiques,
        private BoutiqueContext $context,
    ) {
    }

    /** @return list<OrderOutput>|OrderOutput|null */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|OrderOutput|null
    {
        $request = $context['request'] ?? null;
        $boutique = $this->resolveBoutiqueFromRequest($context, $uriVariables);

        if (!$boutique instanceof Boutique && !$this->context->isSuperAdmin()) {
            return [];
        }

        $orderId = $uriVariables['id'] ?? null;
        if (null !== $orderId) {
            $order = $this->orders->find((string) $orderId);
            if (!$order instanceof Order || ($boutique instanceof Boutique && !$this->belongsToBoutique($order, $boutique))) {
                return null;
            }

            return $this->toOutput($order);
        }

        $status = null;
        $search = null;
        $sortField = 'createdAt';
        $sortDirection = 'DESC';

        if ($request instanceof Request) {
            $statusValue = $request->query->get('status');
            $status = is_string($statusValue) ? OrderStatus::tryFrom($statusValue) : null;
            $searchValue = $request->query->get('customerName');
            $search = is_string($searchValue) ? $searchValue : null;
            $sortFieldValue = $request->query->all('order');
            if (is_array($sortFieldValue) && isset($sortFieldValue['createdAt'])) {
                $sortField = 'createdAt';
                $sortDirection = (string) $sortFieldValue['createdAt'];
            } elseif (is_array($sortFieldValue) && isset($sortFieldValue['totalCents'])) {
                $sortField = 'totalCents';
                $sortDirection = (string) $sortFieldValue['totalCents'];
            } elseif (is_array($sortFieldValue) && isset($sortFieldValue['customerName'])) {
                $sortField = 'customerName';
                $sortDirection = (string) $sortFieldValue['customerName'];
            }
        }

        return array_map(
            [$this, 'toOutput'],
            $this->orders->findForBackoffice($boutique, $status, $search, $sortField, $sortDirection),
        );
    }

    private function belongsToBoutique(Order $order, Boutique $boutique): bool
    {
        return (string) $order->getBoutique()->getId() === (string) $boutique->getId();
    }

    private function toOutput(Order $order): OrderOutput
    {
        $output = new OrderOutput();
        $output->id = (string) $order->getId();
        $output->boutiqueId = (string) $order->getBoutique()->getId();
        $output->customerId = $order->getCustomer() ? (string) $order->getCustomer()->getId() : '';
        $output->customerName = $order->getCustomerName() ?? '';
        $output->customerEmail = $order->getCustomerEmail() ?? '';
        $output->customerPhone = $order->getCustomerPhone();
        $output->channel = $order->getChannel()->value;
        $output->status = $order->getStatus()->value;
        $output->subtotalCents = $order->getSubtotalCents();
        $output->discountCents = $order->getDiscountCents();
        $output->totalCents = $order->getTotalCents();
        $output->currency = $order->getCurrency();
        $output->items = array_map(static fn ($item): array => [
            'productId' => $item->getProduct() ? (string) $item->getProduct()->getId() : null,
            'productName' => $item->getProductName(),
            'sku' => $item->getSku(),
            'quantity' => $item->getQuantity(),
            'unitPriceCents' => $item->getUnitPriceCents(),
            'totalCents' => $item->getUnitPriceCents() * $item->getQuantity(),
            'variantId' => $item->getVariant() ? (string) $item->getVariant()->getId() : null,
            'variantAttributes' => $item->getVariant()
                ? array_map(static fn ($attribute): array => [
                    'name' => $attribute->getAttributeName(),
                    'value' => $attribute->getAttributeValue(),
                ], $item->getVariant()->getAttributes()->toArray())
                : [],
        ], $order->getItems()->toArray());
        $output->shippingAddress = $order->getShippingAddress();
        $output->shippingCity = $order->getShippingCity();
        $output->shippingPostalCode = $order->getShippingPostalCode();
        $output->shippingCountry = $order->getShippingCountry();
        $output->shippingGovernorate = $order->getShippingGovernorate();
        $output->shippingLocality = $order->getShippingLocality();
        $output->deliveryStatus = $order->getDeliveryStatus();
        $output->paymentStatus = $order->getPaymentStatus()->value;
        $output->paymentMethodCode = $order->getPaymentMethodCode();
        $output->deliveryTracking = $order->getDeliveryTracking();
        $output->deliveredAt = $order->getDeliveredAt()?->format(\DateTimeInterface::ATOM);
        $output->createdAt = $order->getCreatedAt();
        $output->updatedAt = $order->getCreatedAt();

        return $output;
    }
}
