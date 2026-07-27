<?php

namespace App\State\Refund;

use App\Dto\Refund\RefundOutput;
use App\Entity\Refund;
use App\Repository\RefundRepository;
use App\Repository\BoutiqueRepository;
use App\Security\BoutiqueContext;
use App\Entity\Boutique;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;

final class RefundProvider implements ProviderInterface
{
    public function __construct(
        private RefundRepository $refunds,
        private BoutiqueRepository $boutiques,
        private BoutiqueContext $context,
        private BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|RefundOutput|null
    {
        if ($operation instanceof GetCollection) {
            return $this->getCollection($operation, $uriVariables, $context);
        }

        $refund = $this->refunds->find($uriVariables['id'] ?? null);
        if (!$refund instanceof Refund || !$this->canAccess($refund, $context)) {
            return null;
        }

        return $this->toOutput($refund);
    }

    public function getCollection(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface
    {
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        $boutique = $this->resolveBoutique($context, $uriVariables);
        if (!$boutique instanceof Boutique) {
            return new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
        }

        $result = $this->refunds->findForBackoffice(
            (string) $boutique->getId(),
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

    private function canAccess(Refund $refund, array $context): bool
    {
        if ($this->context->isSuperAdmin()) {
            return true;
        }

        $boutique = $this->resolveBoutique($context);

        return $boutique instanceof Boutique
            && (string) $refund->getBoutique()->getId() === (string) $boutique->getId();
    }

    /** @param array<string, mixed> $context @param array<string, mixed> $uriVariables */
    private function resolveBoutique(array $context, array $uriVariables = []): ?Boutique
    {
        $request = $context['request'] ?? null;
        $boutique = $request instanceof Request
            ? $request->attributes->get('_boutique')
            : null;
        if ($boutique instanceof Boutique) {
            return $boutique;
        }

        $id = $uriVariables['boutiqueId'] ?? $this->context->getBoutiqueId();

        return null !== $id ? $this->boutiques->find((string) $id) : null;
    }

    private function toOutput(Refund $refund): RefundOutput
    {
        $items = [];
        foreach ($refund->getItems() as $item) {
            $items[] = [
                'id' => (string) $item->getId(),
                'productName' => $item->getProductName(),
                'quantity' => $item->getQuantity(),
                'unitPriceCents' => $item->getUnitPriceCents(),
                'totalCents' => $item->getTotalCents(),
            ];
        }

        return new RefundOutput(
            id: (string) $refund->getId(),
            refundNumber: $refund->getRefundNumber(),
            orderId: (string) $refund->getOrder()->getId(),
            orderNumber: $refund->getOrder()->getId(),
            type: $refund->getType()->value,
            status: $refund->getStatus()->value,
            currency: $refund->getCurrency(),
            subtotalCents: $refund->getSubtotalCents(),
            taxCents: $refund->getTaxCents(),
            totalCents: $refund->getTotalCents(),
            reason: $refund->getReason(),
            processedBy: $refund->getProcessedBy(),
            processedAt: $refund->getProcessedAt()?->format('c'),
            creditNoteId: $refund->getCreditNote() ? (string) $refund->getCreditNote()->getId() : null,
            createdAt: $refund->getCreatedAt()->format('c'),
            updatedAt: $refund->getUpdatedAt()?->format('c'),
            items: $items,
        );
    }
}
