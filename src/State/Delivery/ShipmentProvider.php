<?php

namespace App\State\Delivery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\Delivery\ShipmentOutput;
use App\Entity\Boutique;
use App\Entity\Shipment;
use App\Repository\BoutiqueRepository;
use App\Repository\ShipmentRepository;
use App\Security\BoutiqueContext;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BackofficePaginator;
use App\State\Common\BoutiqueAwareProviderTrait;
use Symfony\Component\HttpFoundation\Request;

final class ShipmentProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private readonly ShipmentRepository $repository,
        private readonly BoutiqueContext $context,
        private readonly BoutiqueRepository $boutiques,
        private readonly BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|ShipmentOutput|null
    {
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        $operationName = $operation->getName() ?? '';

        if ('admin_list_shipments' === $operationName) {
            $result = $this->repository->findForBackoffice(null, $pagination['page'], $pagination['itemsPerPage']);

            return new BackofficePaginator(
                array_map($this->toOutput(...), $result['items']),
                $pagination['page'],
                $pagination['itemsPerPage'],
                $result['total'],
            );
        }

        $boutique = $this->resolveBoutiqueFromRequest($context, $uriVariables);
        if (!$boutique instanceof Boutique) {
            return isset($uriVariables['id'])
                ? null
                : new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
        }

        if (isset($uriVariables['id'])) {
            $entity = $this->repository->find($uriVariables['id']);
            if (!$entity instanceof Shipment || $entity->getBoutique()->getId() !== $boutique->getId()) {
                return null;
            }

            return $this->toOutput($entity);
        }

        $result = $this->repository->findForBackoffice(
            $boutique,
            $pagination['page'],
            $pagination['itemsPerPage'],
        );

        return new BackofficePaginator(
            array_map($this->toOutput(...), $result['items']),
            $pagination['page'],
            $pagination['itemsPerPage'],
            $result['total'],
        );
    }

    public function toOutput(Shipment $entity): ShipmentOutput
    {
        $output = new ShipmentOutput();
        $output->id = (string) $entity->getId();
        $output->boutiqueId = (string) $entity->getBoutique()->getId();
        $output->orderId = (string) $entity->getOrder()->getId();
        $output->deliveryCompanyId = (string) $entity->getDeliveryCompany()->getId();
        $output->deliveryCompanyName = $entity->getDeliveryCompany()->getName();
        $output->credentialId = $entity->getCredential() ? (string) $entity->getCredential()->getId() : null;
        $output->status = $entity->getStatus()->value;
        $output->trackingNumber = $entity->getTrackingNumber();
        $output->labelUrl = $entity->getLabelUrl();
        $output->costCents = $entity->getCostCents();
        $output->errorMessage = $entity->getErrorMessage();
        $output->createdAt = $entity->getCreatedAt()->format('c');
        $output->sentAt = $entity->getSentAt()?->format('c');
        $output->updatedAt = $entity->getUpdatedAt()?->format('c');

        return $output;
    }
}
