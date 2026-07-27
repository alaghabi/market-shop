<?php

namespace App\State\Permission;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\Permission\PermissionOutput;
use App\Entity\Permission;
use App\Repository\PermissionRepository;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProviderInterface<PermissionOutput> */
final class PermissionProvider implements ProviderInterface
{
    public function __construct(
        private readonly PermissionRepository $repository,
        private readonly BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|PermissionOutput|null
    {
        if (isset($uriVariables['id'])) {
            $entity = $this->repository->find($uriVariables['id']);
            if (!$entity) {
                throw new NotFoundHttpException('Permission not found');
            }

            return $this->toOutput($entity);
        }

        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        $module = $context['filters']['module'] ?? $request?->query->get('module');
        $module = is_string($module) ? $module : null;
        $result = $this->repository->findForBackoffice(
            $module,
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

    public function toOutput(Permission $entity): PermissionOutput
    {
        $output = new PermissionOutput();
        $output->id = (string) $entity->getId();
        $output->code = $entity->getCode();
        $output->name = $entity->getName();
        $output->module = $entity->getModule();
        $output->description = $entity->getDescription();
        $output->createdAt = $entity->getCreatedAt()->format('c');

        return $output;
    }
}
