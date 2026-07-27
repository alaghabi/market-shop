<?php

namespace App\State\Order;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Repository\OrderRepository;
use App\Security\BoutiqueContext;
use App\Service\Loyalty\LoyaltyEngine;
use App\Service\NotificationService;
use App\Service\Webhook\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles PATCH operations on OrderResource — status updates with webhook dispatching.
 *
 * @implements ProcessorInterface<object, object|null>
 */
final readonly class OrderProcessor implements ProcessorInterface
{
    public function __construct(
        private OrderRepository $orders,
        private EntityManagerInterface $em,
        private WebhookService $webhookService,
        private LoyaltyEngine $loyaltyEngine,
        private BoutiqueContext $boutiqueContext,
        private NotificationService $notifications,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        $orderId = $uriVariables['id'] ?? null;
        if (null === $orderId) {
            return is_object($data) ? $data : null;
        }

        $order = $this->orders->find($orderId);
        if (!$order instanceof Order) {
            throw new NotFoundHttpException('Order not found.');
        }

        if (!$this->boutiqueContext->canAccessBoutique($order->getBoutique())) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        if ($operation instanceof Delete) {
            if (OrderStatus::Cancelled !== $order->getStatus()) {
                throw new BadRequestHttpException('Only refused orders can be deleted.');
            }

            $this->em->remove($order);
            $this->em->flush();

            return null;
        }

        $previousStatus = $order->getStatus();
        $previousPaymentStatus = $order->getPaymentStatus();
        $boutiqueId = (string) $order->getBoutique()->getId();

        // Apply status change if provided
        $newStatusValue = isset($data->status) ? $data->status : null;
        if (is_string($newStatusValue) && '' !== $newStatusValue) {
            $newStatus = OrderStatus::tryFrom($newStatusValue);
            if (null !== $newStatus && $previousStatus !== $newStatus) {
                match ($newStatus) {
                    OrderStatus::Shipped => $order->markAsShipped(''),
                    OrderStatus::Delivered => $order->markAsDelivered(),
                    OrderStatus::Cancelled => $order->setStatus($newStatus),
                    default => $order->setStatus($newStatus),
                };
            }
        }

        // Apply payment status change if provided
        $newPaymentStatusValue = isset($data->paymentStatus) ? $data->paymentStatus : null;
        if (is_string($newPaymentStatusValue) && '' !== $newPaymentStatusValue) {
            $newPaymentStatus = PaymentStatus::tryFrom($newPaymentStatusValue);
            if (null !== $newPaymentStatus && $previousPaymentStatus !== $newPaymentStatus) {
                $order->setPaymentStatus($newPaymentStatus);
            }
        }

        $this->em->flush();

        $payload = [
            'id' => (string) $order->getId(),
            'status' => $order->getStatus()->value,
            'payment_status' => $order->getPaymentStatus()->value,
            'total_cents' => $order->getTotalCents(),
            'currency' => $order->getCurrency(),
            'customer_name' => $order->getCustomerName(),
            'customer_email' => $order->getCustomerEmail(),
        ];

        // Dispatch order.updated when status changed
        if ($previousStatus !== $order->getStatus()) {
            $this->webhookService->dispatchEvent('order.updated', $payload, $boutiqueId);

            // Dispatch specific status events
            match ($order->getStatus()) {
                OrderStatus::Paid => $this->webhookService->dispatchEvent('order.paid', $payload, $boutiqueId),
                OrderStatus::Shipped => $this->webhookService->dispatchEvent('order.shipped', $payload, $boutiqueId),
                OrderStatus::Delivered => $this->webhookService->dispatchEvent('order.delivered', $payload, $boutiqueId),
                OrderStatus::Cancelled => $this->webhookService->dispatchEvent('order.cancelled', $payload, $boutiqueId),
                default => null,
            };

            if (OrderStatus::Paid === $order->getStatus() && OrderStatus::Paid !== $previousStatus) {
                $this->notifications->dispatchOrderConfirmation($order);
            }

            // Loyalty earn/reversal hooks — LoyaltyEngine is the only service allowed to compute these.
            match ($order->getStatus()) {
                OrderStatus::Paid => $this->loyaltyEngine->earnForOrder($order),
                OrderStatus::Cancelled => $this->loyaltyEngine->reverseForOrder($order, 1.0),
                default => null,
            };
        }

        // Dispatch order.paid when payment status changed to Paid (even if order status didn't change)
        if ($previousPaymentStatus !== $order->getPaymentStatus() && PaymentStatus::Paid === $order->getPaymentStatus()) {
            $this->webhookService->dispatchEvent('order.paid', $payload, $boutiqueId);
            $this->loyaltyEngine->earnForOrder($order);
        }

        return $data;
    }
}
