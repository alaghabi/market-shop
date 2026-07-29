<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Repository\BoutiqueRepository;
use App\Repository\ConversationRepository;
use App\Security\BoutiqueContext;
use App\Service\Chat\ChatAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mercure')]
final class MercureAuthorizationController extends AbstractController
{
    public function __construct(
        private Authorization $authorization,
        private ConversationRepository $conversations,
        private BoutiqueRepository $boutiques,
        private BoutiqueContext $boutiqueContext,
        private ChatAccessService $chatAccess,
        private Security $security,
    ) {
    }

    #[Route('/authorize', name: 'mercure_authorize', methods: ['GET'])]
    public function authorize(Request $request): JsonResponse
    {
        $conversationId = $request->query->get('conversationId');
        if (is_string($conversationId) && '' !== $conversationId) {
            return $this->authorizeConversation($request, $conversationId);
        }

        if (!$this->boutiqueContext->isStaff()) {
            throw new AccessDeniedHttpException('Mercure access denied.');
        }

        return $this->authorizeBackoffice($request);
    }

    private function authorizeConversation(Request $request, string $conversationId): JsonResponse
    {
        $conversation = $this->conversations->find($conversationId);
        if (!$conversation instanceof Conversation) {
            throw new NotFoundHttpException('Conversation not found.');
        }

        if (!$this->chatAccess->canAccessConversation($conversation, $request->headers->get('X-Guest-Chat-Token'))) {
            throw new AccessDeniedHttpException('Conversation access denied.');
        }

        $topic = sprintf('chat/conversation/%s', (string) $conversation->getId());
        $this->setSubscriberCookie($request, [$topic]);

        return $this->json(['authorized' => true]);
    }

    private function authorizeBackoffice(Request $request): JsonResponse
    {
        $boutiqueId = $request->query->get('boutiqueId');
        $topic = 'backoffice/delivery/platform';

        if (is_string($boutiqueId) && '' !== $boutiqueId) {
            $boutique = $this->boutiques->findBySlugOrId($boutiqueId);
            if (null === $boutique || !$this->boutiqueContext->canAccessBoutique($boutique)) {
                throw new AccessDeniedHttpException('Boutique access denied.');
            }

            $topic = sprintf('backoffice/delivery/%s', (string) $boutique->getId());
        } elseif (!$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            $boutiqueId = $this->boutiqueContext->getBoutiqueId();
            $boutique = null !== $boutiqueId ? $this->boutiques->find((string) $boutiqueId) : null;
            if (null === $boutique) {
                throw new AccessDeniedHttpException('Boutique access denied.');
            }

            $topic = sprintf('backoffice/delivery/%s', (string) $boutique->getId());
        }

        $this->setSubscriberCookie($request, [$topic]);

        return $this->json(['authorized' => true]);
    }

    /** @param list<string> $topics */
    private function setSubscriberCookie(Request $request, array $topics): void
    {
        $this->authorization->setCookie(
            $request,
            subscribe: $topics,
            additionalClaims: ['exp' => new \DateTimeImmutable('+5 minutes')],
        );
    }
}
