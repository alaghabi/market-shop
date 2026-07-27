<?php

namespace App\State\SubscriptionPlan;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\SubscriptionPlan\SubscriptionPlanOutput;
use App\Entity\SubscriptionPlan;
use App\Repository\SubscriptionPlanRepository;
use App\Repository\SubscriptionModuleRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProviderInterface<SubscriptionPlanOutput> */
final class SubscriptionPlanProvider implements ProviderInterface
{
    public function __construct(
        private readonly SubscriptionPlanRepository $repository,
        private readonly SubscriptionModuleRepository $subscriptionModules,
    ) {
    }

    /** @return array<SubscriptionPlanOutput>|SubscriptionPlanOutput|null */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|SubscriptionPlanOutput|null
    {
        $operationName = $operation->getName() ?? '';

        if ('boutique_subscription_plans' === $operationName) {
            $entities = $this->repository->findVisibleForBoutique();

            return array_map([$this, 'toOutput'], $entities);
        }

        if (isset($uriVariables['id'])) {
            $entity = $this->repository->find($uriVariables['id']);
            if (!$entity) {
                throw new NotFoundHttpException('Subscription plan not found');
            }

            return $this->toOutput($entity);
        }

        $entities = $this->repository->findBy([], ['priceTnd' => 'ASC']);

        return array_map([$this, 'toOutput'], $entities);
    }

    private function toOutput(SubscriptionPlan $entity): SubscriptionPlanOutput
    {
        $output = new SubscriptionPlanOutput();
        $output->id = (string) $entity->getId();
        $output->name = $entity->getName();
        $output->description = $entity->getDescription();
        $output->durationMonths = $entity->getDurationMonths();
        $output->priceTnd = $entity->getPriceTnd();
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
