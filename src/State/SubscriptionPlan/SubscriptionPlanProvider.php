<?php

namespace App\State\SubscriptionPlan;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\SubscriptionPlan\SubscriptionPlanOutput;
use App\Entity\SubscriptionPlan;
use App\Repository\SubscriptionPlanRepository;
use App\Repository\SubscriptionModuleRepository;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProviderInterface<SubscriptionPlanOutput> */
final class SubscriptionPlanProvider implements ProviderInterface
{
    public function __construct(
        private readonly SubscriptionPlanRepository $repository,
        private readonly SubscriptionModuleRepository $subscriptionModules,
        private readonly BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|SubscriptionPlanOutput|null
    {
        $operationName = $operation->getName() ?? '';
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);

        if ('boutique_subscription_plans' === $operationName || 'public_subscription_plans' === $operationName) {
            $result = $this->repository->findForBackoffice(
                true,
                $pagination['page'],
                $pagination['itemsPerPage'],
            );

            return new BackofficePaginator(
                array_map([$this, 'toOutput'], $result['items']),
                $pagination['page'],
                $pagination['itemsPerPage'],
                $result['total'],
            );
        }

        if (isset($uriVariables['id'])) {
            $entity = $this->repository->find($uriVariables['id']);
            if (!$entity) {
                throw new NotFoundHttpException('Subscription plan not found');
            }

            return $this->toOutput($entity);
        }

        $result = $this->repository->findForBackoffice(
            false,
            $pagination['page'],
            $pagination['itemsPerPage'],
        );

        return new BackofficePaginator(
            array_map([$this, 'toOutput'], $result['items']),
            $pagination['page'],
            $pagination['itemsPerPage'],
            $result['total'],
        );
    }

    private function toOutput(SubscriptionPlan $entity): SubscriptionPlanOutput
    {
        $output = new SubscriptionPlanOutput();
        $output->id = (string) $entity->getId();
        $output->name = $entity->getName();
        $output->description = $entity->getDescription();
        $output->durationMonths = $entity->getDurationMonths();
        $output->priceTnd = $entity->getPriceTnd();
        $output->renewalPriceTnd = $entity->getRenewalPriceTnd();
        $output->effectiveRenewalPriceTnd = $entity->getEffectiveRenewalPriceTnd();
        $output->isFree = $entity->isFree();
        $output->isVisible = $entity->isVisible();
        $output->isActive = $entity->isActive();
        $allowedModuleCodes = $this->subscriptionModules->findAllowedModuleCodes($entity);
        $output->modules = [] !== $allowedModuleCodes ? $allowedModuleCodes : $entity->getModules();
        $output->currency = $entity->getCurrency();
        $output->displayOrder = $entity->getDisplayOrder();
        $output->themes = array_map(
            static fn ($theme) => ['id' => (string) $theme->getId(), 'code' => $theme->getCode(), 'name' => $theme->getName()],
            $entity->getThemes()->toArray(),
        );

        $quotas = [];
        foreach ($entity->getPlanQuotas() as $planQuota) {
            $quotas[] = [
                'quotaCode' => $planQuota->getQuota()->getCode(),
                'quotaName' => $planQuota->getQuota()->getName(),
                'limitValue' => $planQuota->getLimitValue(),
            ];
        }
        $output->quotas = $quotas;

        $output->createdAt = $entity->getCreatedAt()->format('c');
        $output->updatedAt = $entity->getUpdatedAt()?->format('c');

        return $output;
    }
}
