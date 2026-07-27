<?php

namespace App\State\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Notification\NotificationResource;
use App\Entity\Notification;
use App\Repository\NotificationRepository;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BackofficePaginator;
use ApiPlatform\State\Pagination\PaginatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProviderInterface<NotificationResource> */
final class NotificationProvider implements ProviderInterface, ProcessorInterface
{
    public function __construct(
        private readonly NotificationRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly \App\Security\BoutiqueContext $context,
        private readonly BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|NotificationResource|null
    {
        if (isset($uriVariables['id'])) {
            $entity = $this->repository->find($uriVariables['id']);
            $request = $context['request'] ?? null;
            $boutique = $this->scope->resolve($request instanceof Request ? $request : null);

            if (!$entity instanceof Notification || !$this->canAccess($entity, $boutique)) {
                return null;
            }

            return $this->toOutput($entity);
        }

        $recipient = $this->context->getUserIdentifier();
        $isSuperAdmin = $this->context->isSuperAdmin();
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        $result = $this->repository->findForRecipient(
            $recipient,
            $isSuperAdmin,
            $this->scope->resolve($request),
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

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NotificationResource
    {
        $id = $uriVariables['id'] ?? '';
        $entity = $this->repository->find($id);
        $request = $context['request'] ?? null;
        $boutique = $this->scope->resolve($request instanceof Request ? $request : null);
        if (!$entity instanceof Notification || !$this->canAccess($entity, $boutique)) {
            throw new NotFoundHttpException('Notification not found');
        }

        $entity->markAsRead();
        $this->em->flush();

        return $this->toOutput($entity);
    }

    private function canAccess(Notification $entity, ?\App\Entity\Boutique $boutique): bool
    {
        if (!$this->context->isSuperAdmin() && $entity->getRecipientIdentifier() !== $this->context->getUserIdentifier()) {
            return false;
        }

        if ($boutique instanceof \App\Entity\Boutique && $entity->getBoutique() !== $boutique) {
            return false;
        }

        return $this->context->isSuperAdmin() || null !== $entity->getBoutique() && $this->context->canAccessBoutique($entity->getBoutique());
    }

    private function toOutput(Notification $entity): NotificationResource
    {
        $output = new NotificationResource();
        $output->id = (string) $entity->getId();
        $output->recipientIdentifier = $entity->getRecipientIdentifier();
        $output->type = $entity->getType();
        $output->title = $entity->getTitle();
        $output->message = $entity->getMessage();
        $output->boutiqueId = null !== $entity->getBoutique() ? (string) $entity->getBoutique()->getId() : null;
        $output->read = $entity->isRead();
        $output->createdAt = $entity->getCreatedAt()->format('c');

        return $output;
    }
}
