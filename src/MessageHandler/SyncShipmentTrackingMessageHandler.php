<?php

namespace App\MessageHandler;

use App\Entity\Shipment;
use App\Message\SyncShipmentTrackingMessage;
use App\Service\Delivery\DeliveryEngine;
use App\Service\Delivery\DeliveryOutcomeNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SyncShipmentTrackingMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DeliveryEngine $engine,
        private readonly DeliveryOutcomeNotifier $outcomes,
    ) {
    }

    public function __invoke(SyncShipmentTrackingMessage $message): void
    {
        $shipment = $this->em->find(Shipment::class, $message->getShipmentId());
        if (!$shipment instanceof Shipment) {
            return;
        }

        $previousStatus = $shipment->getStatus();
        $result = $this->engine->trackShipment($shipment);
        $this->outcomes->publishShipmentUpdate($shipment->getOrder(), $shipment, $result, 'shipment.tracking');
        if ($previousStatus !== $shipment->getStatus() && \App\Enum\ShipmentStatus::Delivered === $shipment->getStatus()) {
            $this->outcomes->shipmentDelivered($shipment);
        }
    }
}
