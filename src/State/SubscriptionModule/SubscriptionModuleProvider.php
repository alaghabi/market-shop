<?php

namespace App\State\SubscriptionModule;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\SubscriptionModule\SubscriptionModuleOutput;
use App\Entity\SubscriptionModule;
use App\Repository\SubscriptionModuleRepository;
use App\Repository\SubscriptionPlanRepository;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProviderInterface<SubscriptionModuleOutput> */
final class SubscriptionModuleProvider implements ProviderInterface
{
    public function __construct(
        private readonly SubscriptionModuleRepository $repository,
        private readonly SubscriptionPlanRepository $plans,
        private readonly BackofficeScopeResolver $scope,
    ) {
    }

    /** @return PaginatorInterface<SubscriptionModuleOutput>|SubscriptionModuleOutput|null */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|SubscriptionModuleOutput|null
    {
        if (isset($uriVariables['id'])) {
            $entity = $this->repository->find($uriVariables['id']);
            if (!$entity) {
                throw new NotFoundHttpException('Subscription module not found');
            }

            return $this->toOutput($entity);
        }

        if (isset($uriVariables['planId'])) {
            $plan = $this->plans->find($uriVariables['planId']);
            if (!$plan) {
                throw new NotFoundHttpException('Plan not found');
            }

            $request = $context['request'] ?? null;
            $request = $request instanceof Request ? $request : null;
            $pagination = $this->scope->pagination($request);
            $result = $this->repository->findForBackoffice(
                $plan,
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

        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);

        /** @var list<SubscriptionModuleOutput> $items */
        $items = [];

        return new BackofficePaginator($items, $pagination['page'], $pagination['itemsPerPage'], 0);
    }

    public function toOutput(SubscriptionModule $entity): SubscriptionModuleOutput
    {
        $output = new SubscriptionModuleOutput();
        $output->id = (string) $entity->getId();
        $output->planId = (string) $entity->getPlan()->getId();
        $output->planName = $entity->getPlan()->getName();
        $output->moduleId = (string) $entity->getModule()->getId();
        $output->moduleCode = $entity->getModule()->getCode();
        $output->moduleName = $entity->getModule()->getName();
        $output->isAllowed = $entity->isAllowed();
        $output->createdAt = $entity->getCreatedAt()->format('c');
        $output->updatedAt = $entity->getUpdatedAt()?->format('c');

        return $output;
    }
}
