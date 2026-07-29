<?php

namespace App\Service\Delivery;

use App\Entity\Order;
use App\Entity\Shipment;
use App\Service\Notification\BackofficeNotificationService;
use App\Service\Delivery\Connector\DeliveryResult;

final readonly class DeliveryOutcomeNotifier
{
    public function __construct(
        private BackofficeNotificationService $notifications,
        private DeliveryRealtimePublisher $realtime,
    ) {
    }

    public function shipmentProcessed(Order $order, ?Shipment $shipment, DeliveryResult $result): void
    {
        $success = $result->success;
        $tracking = $shipment?->getTrackingNumber() ?? $result->trackingNumber;
        $title = $success ? 'Livraison lancée' : 'Échec de livraison';
        $message = $success
            ? sprintf('La commande #%s a été envoyée au transporteur%s.', substr((string) $order->getId(), 0, 8), null !== $tracking ? ' avec le suivi '.$tracking : '')
            : sprintf('La livraison de la commande #%s a échoué : %s', substr((string) $order->getId(), 0, 8), $result->errorMessage ?? 'Erreur inconnue.');

        $this->notifications->notifyBoutiqueAdmins(
            $order->getBoutique(),
            $success ? 'success' : 'error',
            $title,
            $message,
            $success ? 'order_delivery_created' : 'order_delivery_failed',
        );
        $this->publishShipment($order, $shipment, $result, 'shipment.processed');
    }

    public function shipmentDelivered(Shipment $shipment): void
    {
        $order = $shipment->getOrder();
        $this->notifications->notifyBoutiqueAdmins(
            $order->getBoutique(),
            'success',
            'Commande livrée',
            sprintf('La commande #%s a été livrée.', substr((string) $order->getId(), 0, 8)),
            'delivery.shipment_delivered',
        );
        $this->publishShipment($order, $shipment, null, 'shipment.delivered');
    }

    public function publishShipmentUpdate(Order $order, Shipment $shipment, ?DeliveryResult $result = null, string $event = 'shipment.updated'): void
    {
        $this->publishShipment($order, $shipment, $result, $event);
    }

    private function publishShipment(Order $order, ?Shipment $shipment, ?DeliveryResult $result, string $event): void
    {
        $this->realtime->publish($order->getBoutique(), [
            'event' => $event,
            'orderId' => (string) $order->getId(),
            'shipmentId' => $shipment ? (string) $shipment->getId() : null,
            'status' => $shipment?->getStatus()->value ?? ($result?->success ? 'sent' : 'failed'),
            'trackingNumber' => $shipment?->getTrackingNumber() ?? $result?->trackingNumber,
            'labelUrl' => $shipment?->getLabelUrl() ?? $result?->labelUrl,
            'errorMessage' => $shipment?->getErrorMessage() ?? $result?->errorMessage,
            'orderStatus' => $order->getStatus()->value,
            'deliveryStatus' => $order->getDeliveryStatus(),
        ]);
    }
}
