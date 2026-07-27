<?php

namespace App\Controller\Rest\Auth;

use App\Entity\Boutique;
use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\BoutiqueRepository;
use App\Repository\UserRepository;
use App\Repository\UserShopRepository;
use App\Security\BoutiqueContext;
use App\Service\Notification\BackofficeNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class AdminValidationController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $users,
        private BoutiqueRepository $boutiques,
        private UserShopRepository $userShops,
        private BackofficeNotificationService $notifications,
        private Security $security,
        private BoutiqueContext $boutiqueContext,
    ) {
    }

    #[Route('/api/admin/boutiques/{id}/approve', name: 'api_admin_approve_boutique', methods: ['POST'])]
    public function approveBoutique(string $id): JsonResponse
    {
        if (!$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return new JsonResponse(['message' => 'Accès refusé.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $boutique = $this->boutiques->find($id);
        if (!$boutique instanceof Boutique) {
            return new JsonResponse(['message' => 'Boutique introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $adminEmail = $this->security->getUser()?->getUserIdentifier();
        $boutique->approve($adminEmail);

        foreach ($boutique->getUserShops() as $userShop) {
            $userShop->setStatus(UserStatus::Active);
        }

        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Boutique approuvée avec succès.', 'status' => $boutique->getStatus()->value]);
    }

    #[Route('/api/admin/boutiques/{id}/reject', name: 'api_admin_reject_boutique', methods: ['POST'])]
    public function rejectBoutique(string $id, Request $request): JsonResponse
    {
        if (!$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return new JsonResponse(['message' => 'Accès refusé.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $boutique = $this->boutiques->find($id);
        if (!$boutique instanceof Boutique) {
            return new JsonResponse(['message' => 'Boutique introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        $reason = isset($payload['reason']) ? (string) $payload['reason'] : null;
        $boutique->reject($reason);

        foreach ($boutique->getUserShops() as $userShop) {
            $userShop->setStatus(UserStatus::Rejected);
        }

        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Boutique refusée.', 'status' => $boutique->getStatus()->value, 'rejectionReason' => $reason]);
    }

    #[Route('/api/admin/boutiques/{id}/suspend', name: 'api_admin_suspend_boutique', methods: ['POST'])]
    public function suspendBoutique(string $id): JsonResponse
    {
        if (!$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return new JsonResponse(['message' => 'Accès refusé.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $boutique = $this->boutiques->find($id);
        if (!$boutique instanceof Boutique) {
            return new JsonResponse(['message' => 'Boutique introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $boutique->suspend();
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Boutique suspendue.', 'status' => $boutique->getStatus()->value]);
    }

    #[Route('/api/admin/boutiques/{id}/activate', name: 'api_admin_activate_boutique', methods: ['POST'])]
    public function activateBoutique(string $id): JsonResponse
    {
        if (!$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return new JsonResponse(['message' => 'Accès refusé.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $boutique = $this->boutiques->find($id);
        if (!$boutique instanceof Boutique) {
            return new JsonResponse(['message' => 'Boutique introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $boutique->reactivate();
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Boutique réactivée.', 'status' => $boutique->getStatus()->value]);
    }

    #[Route('/api/admin/boutiques/{id}/archive', name: 'api_admin_archive_boutique', methods: ['POST'])]
    public function archiveBoutique(string $id): JsonResponse
    {
        if (!$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return new JsonResponse(['message' => 'Accès refusé.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $boutique = $this->boutiques->find($id);
        if (!$boutique instanceof Boutique) {
            return new JsonResponse(['message' => 'Boutique introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $boutique->archive();
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Boutique archivée.', 'status' => $boutique->getStatus()->value]);
    }

    #[Route('/api/admin/users/{id}/suspend', name: 'api_admin_suspend_user', methods: ['POST'])]
    public function suspendUser(string $id): JsonResponse
    {
        if (!$this->security->isGranted('ROLE_SUPER_ADMIN') && !$this->security->isGranted('ROLE_BOUTIQUE_ADMIN')) {
            return new JsonResponse(['message' => 'Accès refusé.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $user = $this->users->find($id);
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Utilisateur introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $managedShops = $this->managedUserShops($user);
        if ([] === $managedShops) {
            return new JsonResponse(['message' => 'Utilisateur hors périmètre boutique.'], JsonResponse::HTTP_FORBIDDEN);
        }
        if (!$this->security->isGranted('ROLE_SUPER_ADMIN') && array_filter($managedShops, static fn ($shop): bool => 'ROLE_CAISSIER' !== $shop->getRole())) {
            return new JsonResponse(['message' => 'Seul un super administrateur peut gérer un administrateur boutique.'], JsonResponse::HTTP_FORBIDDEN);
        }

        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            $user->setStatus(UserStatus::Suspended);
            $managedShops = $user->getUserShops()->toArray();
        }

        foreach ($managedShops as $userShop) {
            $userShop->setStatus(UserStatus::Suspended);
        }

        $this->entityManager->flush();

        foreach ($managedShops as $userShop) {
            $this->notifications->notifyUser(
                $user,
                $userShop->getBoutique(),
                'boutique_admin_suspended',
                'Accès administrateur suspendu',
                sprintf('Votre accès administrateur à la boutique "%s" a été suspendu.', $userShop->getBoutique()->getName()),
                'boutique_admin.suspended',
            );
        }

        return new JsonResponse(['message' => 'Utilisateur suspendu.', 'status' => $user->getStatus()->value]);
    }

    #[Route('/api/admin/users/{id}/activate', name: 'api_admin_activate_user', methods: ['POST'])]
    public function activateUser(string $id): JsonResponse
    {
        if (!$this->security->isGranted('ROLE_SUPER_ADMIN') && !$this->security->isGranted('ROLE_BOUTIQUE_ADMIN')) {
            return new JsonResponse(['message' => 'Accès refusé.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $user = $this->users->find($id);
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Utilisateur introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $managedShops = $this->managedUserShops($user);
        if ([] === $managedShops) {
            return new JsonResponse(['message' => 'Utilisateur hors périmètre boutique.'], JsonResponse::HTTP_FORBIDDEN);
        }
        if (!$this->security->isGranted('ROLE_SUPER_ADMIN') && array_filter($managedShops, static fn ($shop): bool => 'ROLE_CAISSIER' !== $shop->getRole())) {
            return new JsonResponse(['message' => 'Seul un super administrateur peut gérer un administrateur boutique.'], JsonResponse::HTTP_FORBIDDEN);
        }

        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            $user->setStatus(UserStatus::Active);
            $managedShops = $user->getUserShops()->toArray();
        }

        foreach ($managedShops as $userShop) {
            $userShop->setStatus(UserStatus::Active);
        }

        $this->entityManager->flush();

        foreach ($managedShops as $userShop) {
            $this->notifications->notifyUser(
                $user,
                $userShop->getBoutique(),
                'boutique_admin_activated',
                'Accès administrateur activé',
                sprintf('Votre accès administrateur à la boutique "%s" a été activé.', $userShop->getBoutique()->getName()),
                'boutique_admin.activated',
            );
        }

        return new JsonResponse(['message' => 'Utilisateur activé.', 'status' => $user->getStatus()->value]);
    }

    /** @return list<\App\Entity\UserShop> */
    private function managedUserShops(User $user): array
    {
        return array_values(array_filter(
            $user->getUserShops()->toArray(),
            fn ($userShop): bool => $this->boutiqueContext->canAccessBoutique($userShop->getBoutique()),
        ));
    }

    #[Route('/api/admin/pending-boutiques', name: 'api_admin_pending_boutiques', methods: ['GET'])]
    public function pendingBoutiques(): JsonResponse
    {
        if (!$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return new JsonResponse(['message' => 'Accès refusé.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $pendingBoutiques = $this->boutiques->findPendingValidation();

        $result = array_map(function (Boutique $boutique) {
            $ownerShop = null;
            foreach ($boutique->getUserShops() as $us) {
                if (in_array('ROLE_BOUTIQUE_ADMIN', $us->getUser()->getRoles())) {
                    $ownerShop = $us;
                    break;
                }
            }

            return [
                'id' => (string) $boutique->getId(),
                'name' => $boutique->getName(),
                'slug' => $boutique->getSlug(),
                'status' => $boutique->getStatus()->value,
                'createdAt' => $boutique->getCreatedAt()->format('c'),
                'owner' => $ownerShop ? [
                    'id' => (string) $ownerShop->getUser()->getId(),
                    'email' => $ownerShop->getUser()->getUserIdentifier(),
                    'firstname' => $ownerShop->getUser()->getFirstname(),
                    'lastname' => $ownerShop->getUser()->getLastname(),
                    'phone' => $ownerShop->getUser()->getPhone(),
                ] : null,
            ];
        }, $pendingBoutiques);

        return new JsonResponse(['boutiques' => $result]);
    }
}
