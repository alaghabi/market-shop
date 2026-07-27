<?php

namespace App\State\DeliveryRule;

use App\Dto\DeliveryRule\DeliveryRuleOutput;
use App\Entity\DeliveryRule;
use App\Repository\DeliveryRuleRepository;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BoutiqueAwareProviderTrait;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;

final class DeliveryRuleProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private DeliveryRuleRepository $rules,
        private BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|DeliveryRuleOutput|null
    {
        if ($operation instanceof GetCollection) {
            $request = $context['request'] ?? null;
            $request = $request instanceof Request ? $request : null;
            $pagination = $this->scope->pagination($request);
            $boutique = $this->resolveBoutiqueFromRequest($context);
            if (!$boutique) {
                return new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
            }

            $result = $this->rules->findForBackoffice(
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

        $rule = $this->rules->find($uriVariables['id'] ?? null);
        if (!$rule instanceof DeliveryRule) {
            return null;
        }

        $boutique = $this->resolveBoutiqueFromRequest($context);
        if ($boutique && $rule->getBoutique()->getId() !== $boutique->getId()) {
            return null;
        }

        return $this->toOutput($rule);
    }

    private function toOutput(DeliveryRule $rule): DeliveryRuleOutput
    {
        return new DeliveryRuleOutput(
            id: (string) $rule->getId(),
            name: $rule->getName(),
            type: $rule->getType()->value,
            priceCents: $rule->getPriceCents(),
            minWeightKg: $rule->getMinWeightKg(),
            maxWeightKg: $rule->getMaxWeightKg(),
            minDistanceKm: $rule->getMinDistanceKm(),
            maxDistanceKm: $rule->getMaxDistanceKm(),
            minCartAmountCents: $rule->getMinCartAmountCents(),
            maxCartAmountCents: $rule->getMaxCartAmountCents(),
            priority: $rule->getPriority(),
            isActive: $rule->isActive(),
            createdAt: $rule->getCreatedAt()->format('c'),
            updatedAt: $rule->getUpdatedAt()?->format('c'),
        );
    }
}
