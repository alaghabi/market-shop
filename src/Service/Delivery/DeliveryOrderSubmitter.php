<?php

namespace App\Service\Delivery;

use App\Entity\Order;
use App\Repository\ShipmentRepository;

/**
 * Thin adapter kept for the retry command: decides whether an order should go through the real
 * connector pipeline (DeliveryEngine) or the fake/no-API fallback used when
 * a boutique hasn't enabled the delivery API integration.
 */
final class DeliveryOrderSubmitter
{
    public function __construct(
        private readonly DeliveryEngine $engine,
        private readonly ShipmentRepository $shipments,
        private readonly DeliveryOutcomeNotifier $outcomes,
    ) {
    }

    /** @return array{success: bool, tracking?: string, error?: string} */
    public function submit(Order $order): array
    {
        $boutique = $order->getBoutique();
        $settings = $boutique->getSettings();

        if (null === $settings || !$settings->useDeliveryApi()) {
            $order->markAsShipped(sprintf('FAKE-%s', strtoupper(bin2hex(random_bytes(4)))));

            return ['success' => true, 'tracking' => $order->getDeliveryTracking() ?? ''];
        }

        $result = $this->engine->createShipmentForOrder($order);
        $this->outcomes->shipmentProcessed($order, $this->shipments->findOneByOrder($order), $result);

        return $result->success
            ? ['success' => true, 'tracking' => $result->trackingNumber ?? '']
            : ['success' => false, 'error' => $result->errorMessage ?? 'Erreur inconnue'];
    }
}
