<?php

namespace App\State\Delivery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Delivery\ShipmentCreateInput;
use App\Dto\Delivery\ShipmentOutput;
use App\Dto\Delivery\ShipmentQueueOutput;
use App\Entity\BoutiqueDeliveryAccount;
use App\Entity\Order;
use App\Entity\Shipment;
use App\Message\CreateShipmentMessage;
use App\Repository\OrderRepository;
use App\Repository\ShipmentRepository;
use App\Security\BoutiqueContext;
use App\Service\Audit\AuditLogService;
use App\Service\Delivery\DeliveryEngine;
use App\Service\Delivery\DeliveryOutcomeNotifier;
use App\Service\Delivery\DeliveryPaymentPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

final class ShipmentProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ShipmentRepository $repository,
        private readonly OrderRepository $orders,
        private readonly EntityManagerInterface $em,
        private readonly BoutiqueContext $context,
        private readonly DeliveryEngine $engine,
        private readonly ShipmentProvider $provider,
        private readonly MessageBusInterface $bus,
        private readonly AuditLogService $auditLog,
        private readonly Security $security,
        private readonly DeliveryPaymentPolicy $paymentPolicy,
        private readonly DeliveryOutcomeNotifier $outcomes,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $operationName = $operation->getName() ?? '';

        return match ($operationName) {
            'create_shipment' => $this->create($data),
            'queue_shipment' => $this->queue($data),
            'cancel_shipment' => $this->cancel((string) $uriVariables['id']),
            'track_shipment' => $this->track((string) $uriVariables['id']),
            'label_shipment' => $this->label((string) $uriVariables['id']),
            default => null,
        };
    }

    private function create(ShipmentCreateInput $input): ShipmentOutput
    {
        [$order, $account] = $this->resolveOrderAndAccount($input);

        $result = $this->engine->createShipmentForOrder($order, $account);

        $shipment = $this->repository->findOneByOrder($order);
        if (!$shipment instanceof Shipment) {
            throw new NotFoundHttpException('Shipment could not be created');
        }

        $this->audit('shipment.create', $shipment);
        $this->outcomes->shipmentProcessed($order, $shipment, $result);

        return $this->provider->toOutput($shipment);
    }

    private function queue(ShipmentCreateInput $input): ShipmentQueueOutput
    {
        [$order] = $this->resolveOrderAndAccount($input);

        $this->bus->dispatch(new CreateShipmentMessage($input->orderId, $input->accountId));

        $ack = new ShipmentQueueOutput();
        $ack->orderId = (string) $order->getId();

        $this->audit('shipment.queue', null, (string) $order->getId(), (string) $order->getBoutique()->getId());

        return $ack;
    }

    /** @return array{0: Order, 1: ?BoutiqueDeliveryAccount} */
    private function resolveOrderAndAccount(ShipmentCreateInput $input): array
    {
        $order = $this->orders->find($input->orderId);
        if (!$order instanceof Order) {
            throw new NotFoundHttpException('Order not found');
        }

        if (!$this->context->canAccessBoutique($order->getBoutique())) {
            throw new AccessDeniedHttpException('Access denied');
        }

        if (!$this->paymentPolicy->canSubmit($order)) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('La commande doit être payée avant son envoi au transporteur.');
        }

        $account = null;
        if (null !== $input->accountId) {
            $account = $this->em->find(BoutiqueDeliveryAccount::class, $input->accountId);
        }

        return [$order, $account instanceof BoutiqueDeliveryAccount ? $account : null];
    }

    private function cancel(string $id): ShipmentOutput
    {
        $shipment = $this->findOwnedShipment($id);
        $this->engine->cancelShipment($shipment);
        $this->audit('shipment.cancel', $shipment);

        return $this->provider->toOutput($shipment);
    }

    private function track(string $id): ShipmentOutput
    {
        $shipment = $this->findOwnedShipment($id);
        $previousStatus = $shipment->getStatus();
        $result = $this->engine->trackShipment($shipment);
        $this->audit('shipment.track', $shipment);
        $this->outcomes->publishShipmentUpdate($shipment->getOrder(), $shipment, $result, 'shipment.tracking');
        if ($previousStatus !== $shipment->getStatus() && \App\Enum\ShipmentStatus::Delivered === $shipment->getStatus()) {
            $this->outcomes->shipmentDelivered($shipment);
        }

        return $this->provider->toOutput($shipment);
    }

    private function label(string $id): ShipmentOutput
    {
        $shipment = $this->findOwnedShipment($id);
        $result = $this->engine->getLabel($shipment);
        $this->audit('shipment.label', $shipment);
        $this->outcomes->publishShipmentUpdate($shipment->getOrder(), $shipment, $result, 'shipment.label');

        return $this->provider->toOutput($shipment);
    }

    private function findOwnedShipment(string $id): Shipment
    {
        $shipment = $this->repository->find($id);
        if (!$shipment instanceof Shipment) {
            throw new NotFoundHttpException('Shipment not found');
        }

        if (!$this->context->canAccessBoutique($shipment->getBoutique())) {
            throw new AccessDeniedHttpException('Access denied');
        }

        return $shipment;
    }

    private function audit(string $action, ?Shipment $shipment = null, ?string $orderId = null, ?string $boutiqueId = null): void
    {
        $user = $this->security->getUser();
        $roles = $user?->getRoles() ?? [];
        $this->auditLog->log(
            actorEmail: $user?->getUserIdentifier() ?? 'system',
            actorRole: $roles[0] ?? 'ROLE_USER',
            action: $action,
            resourceType: 'Shipment',
            resourceId: $shipment ? (string) $shipment->getId() : $orderId,
            details: [
                'orderId' => $shipment ? (string) $shipment->getOrder()->getId() : $orderId,
                'trackingNumber' => $shipment?->getTrackingNumber(),
                'status' => $shipment?->getStatus()->value,
            ],
            boutiqueId: $shipment ? (string) $shipment->getBoutique()->getId() : $boutiqueId,
        );
    }
}
