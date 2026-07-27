<?php

namespace App\State\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\UserShop\UserShopOutput;
use App\Entity\UserShop;
use App\Repository\UserShopRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Security\BoutiqueContext;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BackofficePaginator;

final class UserShopProvider implements ProviderInterface
{
    public function __construct(
        private readonly UserShopRepository $repository,
        private readonly BoutiqueContext $boutiqueContext,
        private readonly BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|UserShopOutput|null
    {
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        if ($this->boutiqueContext->isSuperAdmin() && '/admin/boutique-admins' === $operation->getUriTemplate()) {
            $filters = $context['filters'] ?? [];
            $boutiqueId = is_array($filters) ? ($filters['boutiqueId'] ?? null) : null;
            if (is_string($boutiqueId) && '' !== $boutiqueId) {
                $result = $this->repository->findForBackoffice(
                    'ROLE_BOUTIQUE_ADMIN',
                    $boutiqueId,
                    [],
                    $pagination['page'],
                    $pagination['itemsPerPage'],
                );
            } else {
                $result = $this->repository->findForBackoffice(
                    'ROLE_BOUTIQUE_ADMIN',
                    null,
                    [],
                    $pagination['page'],
                    $pagination['itemsPerPage'],
                );
            }

            return new BackofficePaginator(
                array_map([$this, 'toOutput'], $result['items']),
                $pagination['page'],
                $pagination['itemsPerPage'],
                $result['total'],
            );
        }

        if (isset($uriVariables['id'])) {
            $entity = $this->repository->find($uriVariables['id']);

            if ($entity instanceof UserShop && !$this->boutiqueContext->canAccessBoutique($entity->getBoutique())) {
                return null;
            }

            return $entity ? $this->toOutput($entity) : null;
        }

        $request = $context['request'] ?? null;
        $requestedBoutiqueId = $request instanceof Request
            ? $request->query->get('boutiqueId')
            : null;

        if (is_string($requestedBoutiqueId) && '' !== $requestedBoutiqueId) {
            $allowedBoutiqueIds = array_map(
                static fn ($id): string => (string) $id,
                $this->boutiqueContext->getBoutiqueIds(),
            );

            if (!$this->boutiqueContext->isSuperAdmin() && !in_array($requestedBoutiqueId, $allowedBoutiqueIds, true)) {
                return new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
            }

            $result = $this->repository->findForBackoffice(
                null,
                $requestedBoutiqueId,
                [],
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

        $boutiqueIds = $this->boutiqueContext->getBoutiqueIds();
        if ([] === $boutiqueIds) {
            return new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
        }

        $result = $this->repository->findForBackoffice(
            null,
            null,
            array_map('strval', $boutiqueIds),
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

    private function toOutput(UserShop $entity): UserShopOutput
    {
        $output = new UserShopOutput();
        $output->id = (string) $entity->getId();
        $output->userId = (string) $entity->getUser()->getId();
        $output->email = $entity->getUser()->getUserIdentifier();
        $output->displayName = $entity->getUser()->getDisplayName();
        $output->boutiqueId = (string) $entity->getBoutique()->getId();
        $output->boutiqueName = $entity->getBoutique()->getName();
        $output->role = $entity->getRole();
        $output->status = $entity->getStatus()->value;
        $output->createdAt = $entity->getCreatedAt();

        return $output;
    }
}
