<?php

namespace App\State\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Chat\ConversationListResource;
use App\Entity\Conversation;
use App\Repository\ConversationRepository;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BackofficePaginator;
use ApiPlatform\State\Pagination\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/** @implements ProviderInterface<ConversationListResource> */
final class ConversationListProvider implements ProviderInterface
{
    public function __construct(
        private ConversationRepository $repository,
        private TokenStorageInterface $tokenStorage,
        private BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if (null === $user || !is_object($user)) {
            return new BackofficePaginator([], 1, 20, 0);
        }

        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        $result = $this->repository->findForBackoffice(
            $this->scope->resolve($request),
            $pagination['page'],
            $pagination['itemsPerPage'],
        );
        $conversations = $result['items'];

        return new BackofficePaginator(
            array_map(fn (Conversation $c) => $this->mapSingle($c), $conversations),
            $pagination['page'],
            $pagination['itemsPerPage'],
            $result['total'],
        );
    }

    private function mapSingle(Conversation $conversation): ConversationListResource
    {
        $resource = new ConversationListResource();
        $resource->id = (string) $conversation->getId();
        $resource->boutiqueId = (string) $conversation->getBoutique()->getId();
        $resource->boutiqueName = $conversation->getBoutique()->getName();
        $resource->userDisplayName = $conversation->getUser()?->getDisplayName();
        $resource->guestName = $conversation->getGuestName();
        $resource->guestEmail = $conversation->getGuestEmail();

        $last = $conversation->getLastMessage();
        if (null !== $last) {
            $resource->lastMessage = mb_substr($last->getContent(), 0, 120);
            $resource->lastMessageAt = $last->getCreatedAt()->format('c');
        }

        $resource->unreadCount = $conversation->getUnreadCount();
        $resource->active = $conversation->isActive();
        $resource->createdAt = $conversation->getCreatedAt()->format('c');

        return $resource;
    }
}
