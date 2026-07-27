<?php

namespace App\State\Chat;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Chat\ConversationResource;
use App\Entity\Conversation;
use App\Entity\Boutique;
use App\Entity\User;
use App\Repository\BoutiqueRepository;
use App\Repository\ConversationRepository;
use App\Service\Chat\ChatAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/** @implements ProcessorInterface<ConversationResource> */
final class ConversationProcessor implements ProcessorInterface
{
    public function __construct(
        private ConversationRepository $repository,
        private BoutiqueRepository $boutiqueRepository,
        private TokenStorageInterface $tokenStorage,
        private ChatAccessService $access,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ConversationResource
    {
        $id = $uriVariables['id'] ?? null;

        if (null !== $id) {
            $conversation = $this->repository->find($id);

            if (!$conversation instanceof Conversation) {
                throw new NotFoundHttpException('Conversation not found');
            }

            if (!$this->access->canAccessConversation($conversation, $this->getGuestToken($context))) {
                throw new AccessDeniedHttpException('Conversation access denied');
            }

            if ($operation instanceof Delete) {
                $this->em->remove($conversation);
                $this->em->flush();

                return null;
            }

            $conversation->setActive($data->active ?? $conversation->isActive());

            if (null !== $data->guestName) {
                $conversation->setGuestName($data->guestName);
            }
            if (null !== $data->guestEmail) {
                $conversation->setGuestEmail($data->guestEmail);
            }
            if (null !== $data->guestPhone) {
                $conversation->setGuestPhone($data->guestPhone);
            }

            $this->repository->save($conversation, true);

            return $this->mapToResource($conversation);
        }

        $boutiqueId = $uriVariables['boutiqueId'] ?? $data->boutiqueId ?? null;
        $boutique = $this->boutiqueRepository->find($boutiqueId);

        if (!$boutique instanceof Boutique) {
            throw new NotFoundHttpException('Boutique not found');
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        $conversation = new Conversation($boutique, $user instanceof User ? $user : null);

        if (!$user instanceof User) {
            $conversation->generateGuestAccessToken();
        }

        if (null !== $data->guestName) {
            $conversation->setGuestName($data->guestName);
        }
        if (null !== $data->guestEmail) {
            $conversation->setGuestEmail($data->guestEmail);
        }
        if (null !== $data->guestPhone) {
            $conversation->setGuestPhone($data->guestPhone);
        }

        $this->repository->save($conversation, true);

        return $this->mapToResource($conversation);
    }

    private function getGuestToken(array $context): ?string
    {
        $request = $context['request'] ?? null;

        return $request instanceof Request ? $request->headers->get('X-Guest-Chat-Token') : null;
    }

    private function mapToResource(Conversation $conversation): ConversationResource
    {
        $resource = new ConversationResource();
        $resource->id = (string) $conversation->getId();
        $resource->boutiqueId = (string) $conversation->getBoutique()->getId();
        $resource->userId = $conversation->getUser()?->getId()?->jsonSerialize();
        $resource->guestName = $conversation->getGuestName();
        $resource->guestEmail = $conversation->getGuestEmail();
        $resource->guestPhone = $conversation->getGuestPhone();
        $resource->guestAccessToken = $conversation->getGuestAccessToken();
        $resource->active = $conversation->isActive();
        $resource->createdAt = $conversation->getCreatedAt()->format('c');
        $resource->updatedAt = $conversation->getUpdatedAt()?->format('c');
        $resource->unreadCount = $conversation->getUnreadCount();

        return $resource;
    }
}
