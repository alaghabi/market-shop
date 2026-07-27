<?php

namespace App\State\PlatformModule;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\PlatformModule\PlatformModuleOutput;
use App\Entity\PlatformModule;
use App\Repository\PlatformModuleRepository;
use App\Repository\SubscriptionPlanModuleRepository;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProviderInterface<PlatformModuleOutput> */
final class PlatformModuleProvider implements ProviderInterface
{
    public function __construct(
        private readonly PlatformModuleRepository $repository,
        private readonly SubscriptionPlanModuleRepository $modules,
        private readonly BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|PlatformModuleOutput|null
    {
        if (isset($uriVariables['id'])) {
            $entity = $this->repository->find($uriVariables['id']);
            if (!$entity) {
                throw new NotFoundHttpException('Platform module not found');
            }

            return $this->toOutput($entity);
        }

        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        $platformModules = [];
        foreach ($this->repository->findAll() as $entity) {
            $platformModules[$entity->getModule()->getCode()] = $entity;
        }

        $result = $this->modules->findForBackoffice(
            $pagination['page'],
            $pagination['itemsPerPage'],
        );

        return new BackofficePaginator(
            array_map(
                function ($module) use ($platformModules): PlatformModuleOutput {
                    $entity = $platformModules[$module->getCode()] ?? null;
                    if ($entity instanceof PlatformModule) {
                        return $this->toOutput($entity);
                    }

                    $output = new PlatformModuleOutput();
                    $output->moduleId = (string) $module->getId();
                    $output->moduleCode = $module->getCode();
                    $output->moduleName = $module->getName();
                    $output->isEnabled = true;

                    return $output;
                },
                $result['items'],
            ),
            $pagination['page'],
            $pagination['itemsPerPage'],
            $result['total'],
        );
    }

    private function toOutput(PlatformModule $entity): PlatformModuleOutput
    {
        $output = new PlatformModuleOutput();
        $output->id = (string) $entity->getId();
        $output->moduleId = (string) $entity->getModule()->getId();
        $output->moduleCode = $entity->getModule()->getCode();
        $output->moduleName = $entity->getModule()->getName();
        $output->isEnabled = $entity->isEnabled();
        $output->reasonDisabled = $entity->getReasonDisabled();
        $output->createdAt = $entity->getCreatedAt()->format('c');
        $output->updatedAt = $entity->getUpdatedAt()?->format('c');

        return $output;
    }
}
