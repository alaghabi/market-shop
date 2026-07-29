<?php

namespace App\MessageHandler;

use App\Entity\BoutiqueDeliveryAccount;
use App\Entity\Order;
use App\Message\CreateShipmentMessage;
use App\Repository\ShipmentRepository;
use App\Service\Delivery\DeliveryEngine;
use App\Service\Delivery\DeliveryOutcomeNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateShipmentMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DeliveryEngine $engine,
        private readonly ShipmentRepository $shipments,
        private readonly DeliveryOutcomeNotifier $outcomes,
    ) {
    }

    public function __invoke(CreateShipmentMessage $message): void
    {
        $order = $this->em->find(Order::class, $message->getOrderId());
        if (!$order instanceof Order) {
            return;
        }

        $account = null;
        if (null !== $message->getAccountId()) {
            $account = $this->em->find(BoutiqueDeliveryAccount::class, $message->getAccountId());
        }

        $result = $this->engine->createShipmentForOrder($order, $account instanceof BoutiqueDeliveryAccount ? $account : null);
        $this->outcomes->shipmentProcessed($order, $this->shipments->findOneByOrder($order), $result);
    }
}
