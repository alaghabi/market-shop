<?php

namespace App\State\SubscriptionModule;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\SubscriptionModule\SubscriptionModuleInput;
use App\Dto\SubscriptionModule\SubscriptionModuleOutput;
use App\Entity\SubscriptionModule;
use App\Repository\SubscriptionModuleRepository;
use App\Repository\SubscriptionPlanRepository;
use App\Repository\SubscriptionPlanModuleRepository;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\Service\Module\ModuleCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProcessorInterface<SubscriptionModuleInput, SubscriptionModuleOutput|null> */
final class SubscriptionModuleProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly SubscriptionModuleRepository $repository,
        private readonly SubscriptionPlanRepository $plans,
        private readonly SubscriptionPlanModuleRepository $modules,
        private readonly EntityManagerInterface $em,
        private readonly ModuleCacheService $cache,
        private readonly BackofficeScopeResolver $scope,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?SubscriptionModuleOutput
    {
        if (isset($uriVariables['id'])) {
            if ('DELETE' === ($context['request']?->getMethod() ?? '')) {
                $this->delete((string) $uriVariables['id']);

                return null;
            }

            return $this->update((string) $uriVariables['id'], $data);
        }

        return $this->create($data);
    }

    private function create(mixed $data): SubscriptionModuleOutput
    {
        if (!$data instanceof SubscriptionModuleInput) {
            throw new \InvalidArgumentException('Expected SubscriptionModuleInput');
        }

        $plan = $this->findPlan($data->planId);
        $module = $this->findModule($data->moduleId);

        $entity = new SubscriptionModule(
            plan: $plan,
            module: $module,
            isAllowed: $data->isAllowed,
        );
        $this->em->persist($entity);
        $this->em->flush();

        $this->cache->deletePlanModules((string) $plan->getId());

        return (new SubscriptionModuleProvider($this->repository, $this->plans, $this->scope))->toOutput($entity);
    }

    private function update(string $id, mixed $data): SubscriptionModuleOutput
    {
        $entity = $this->findEntity($id);

        if (!$data instanceof SubscriptionModuleInput) {
            throw new \InvalidArgumentException('Expected SubscriptionModuleInput');
        }

        $entity->setAllowed($data->isAllowed);

        $this->em->flush();

        $this->cache->deletePlanModules((string) $entity->getPlan()->getId());

        return (new SubscriptionModuleProvider($this->repository, $this->plans, $this->scope))->toOutput($entity);
    }

    private function delete(string $id): void
    {
        $entity = $this->findEntity($id);
        $planId = (string) $entity->getPlan()->getId();
        $this->em->remove($entity);
        $this->em->flush();

        $this->cache->deletePlanModules($planId);
    }

    private function findEntity(string $id): SubscriptionModule
    {
        $entity = $this->repository->find($id);
        if (!$entity) {
            throw new NotFoundHttpException('Subscription module not found');
        }

        return $entity;
    }

    private function findPlan(string $id): \App\Entity\SubscriptionPlan
    {
        $entity = $this->plans->find($id);
        if (!$entity) {
            throw new NotFoundHttpException('Plan not found');
        }

        return $entity;
    }

    private function findModule(string $id): \App\Entity\SubscriptionPlanModule
    {
        $entity = $this->modules->find($id);
        if (!$entity) {
            throw new NotFoundHttpException('Module not found');
        }

        return $entity;
    }
}
