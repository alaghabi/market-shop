<?php

namespace App\Controller\Rest\Auth;

use App\Entity\Boutique;
use App\Entity\User;
use App\Entity\UserShop;
use App\Enum\BoutiqueStatus;
use App\Enum\UserStatus;
use App\Repository\BoutiqueRepository;
use App\Repository\UserRepository;
use App\Security\LocalTokenManager;
use App\Service\NotificationService;
use App\Service\Auth\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class AuthController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $users,
        private BoutiqueRepository $boutiques,
        private LocalTokenManager $tokens,
        private NotificationService $notifications,
        private EmailVerificationService $emailVerification,
        private Security $security,
    ) {
    }

    #[Route('/api/auth/admin-create-user', name: 'api_auth_admin_create_user', methods: ['POST'])]
    public function adminCreateUser(Request $request): JsonResponse
    {
        if (!$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return new JsonResponse(['message' => 'Seul le Super Admin peut créer des utilisateurs.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $payload = $this->jsonPayload($request);
        $email = $this->normalizeEmail((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $displayName = trim((string) ($payload['displayName'] ?? '')) ?: null;
        $firstname = trim((string) ($payload['firstname'] ?? ''));
        $lastname = trim((string) ($payload['lastname'] ?? ''));
        $phone = trim((string) ($payload['phone'] ?? '')) ?: null;
        $roles = $payload['roles'] ?? ['ROLE_BOUTIQUE_ADMIN'];
        $boutiqueId = $payload['boutiqueId'] ?? null;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            return new JsonResponse(['message' => 'Email valide et mot de passe 8 caractères minimum.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($this->users->findOneBy(['identifier' => $email]) instanceof User) {
            return new JsonResponse(['message' => 'Un compte existe déjà pour cet email.'], JsonResponse::HTTP_CONFLICT);
        }

        $boutique = null;
        if ($boutiqueId) {
            $boutique = $this->boutiques->find($boutiqueId);
            if (!$boutique instanceof Boutique) {
                return new JsonResponse(['message' => 'Boutique introuvable.'], JsonResponse::HTTP_NOT_FOUND);
            }
        }

        $user = new User($boutique, $email, $roles, $displayName, null, null, $firstname ?: null, $lastname ?: null, $phone);
        $user->setPassword($password);
        $user->setStatus(UserStatus::Active);
        $this->entityManager->persist($user);

        if ($boutique) {
            $userShop = new UserShop($user, $boutique, $roles[0] ?? 'ROLE_BOUTIQUE_ADMIN', UserStatus::Active);
            $this->entityManager->persist($userShop);
        }

        $this->entityManager->flush();

        if ($this->requiresEmailVerification($user) && !$user->isEmailVerified()) {
            $this->emailVerification->issue($user, $boutique);
        }

        return new JsonResponse([
            'message' => 'Utilisateur créé avec succès.',
            'user' => [
                'email' => $user->getUserIdentifier(),
                'displayName' => $user->getDisplayName(),
                'firstname' => $user->getFirstname(),
                'lastname' => $user->getLastname(),
                'roles' => $user->getRoles(),
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $email = $this->normalizeEmail((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $user = $this->users->findOneBy(['identifier' => $email]);

        if (!$user instanceof User || !$user->isPasswordValid($password)) {
            return new JsonResponse(['message' => 'Identifiants invalides.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if ($this->requiresEmailVerification($user) && !$user->isEmailVerified()) {
            return new JsonResponse(['message' => 'Veuillez vérifier votre adresse email avant de vous connecter.', 'code' => 'email_verification_required'], JsonResponse::HTTP_FORBIDDEN);
        }

        if (UserStatus::Suspended === $user->getStatus()) {
            return new JsonResponse(['message' => 'Compte suspendu.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $user->markLoggedIn();
        $this->entityManager->flush();

        return $this->authResponse($user);
    }

    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $displayName = trim((string) ($payload['displayName'] ?? '')) ?: null;
        $boutiqueName = trim((string) ($payload['boutiqueName'] ?? ''));
        $boutiqueSlug = $this->slugify((string) ($payload['boutiqueSlug'] ?? $boutiqueName));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || '' === $boutiqueName || '' === $boutiqueSlug) {
            return new JsonResponse(['message' => 'Email, mot de passe et boutique sont obligatoires.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($this->users->findOneBy(['identifier' => $email]) instanceof User) {
            return new JsonResponse(['message' => 'Un compte existe déjà pour cet email.'], JsonResponse::HTTP_CONFLICT);
        }

        if ($this->boutiques->findOneBy(['slug' => $boutiqueSlug]) instanceof Boutique) {
            return new JsonResponse(['message' => 'Ce slug boutique est déjà utilisé.'], JsonResponse::HTTP_CONFLICT);
        }

        $boutique = new Boutique($boutiqueName, $boutiqueSlug);
        $boutique->setStatus(BoutiqueStatus::Pending);
        $user = new User($boutique, $email, ['ROLE_BOUTIQUE_ADMIN'], $displayName);
        $user->setPassword($password);

        $userShop = new UserShop($user, $boutique, 'ROLE_BOUTIQUE_ADMIN', UserStatus::Pending);

        $this->entityManager->persist($boutique);
        $this->entityManager->persist($user);
        $this->entityManager->persist($userShop);

        $this->notifications->notify(
            null,
            'boutique_created',
            'Nouvelle boutique en attente',
            sprintf('La boutique "%s" a été créée par %s et attend la vérification de son adresse email.', $boutique->getName(), $email),
            $boutique,
        );
        $this->notifications->notify(
            $email,
            'boutique_pending',
            'Vérification email nécessaire',
            sprintf('Votre boutique "%s" a été enregistrée. Vérifiez votre email, puis demandez sa publication depuis votre back-office.', $boutique->getName()),
            $boutique,
        );
        $this->entityManager->flush();

        $this->emailVerification->issue($user, $boutique);

        return new JsonResponse([
            'message' => 'Compte créé. Vérifiez votre email pour activer votre accès. La boutique pourra ensuite demander sa publication.',
            'verificationRequired' => true,
        ], JsonResponse::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    private function jsonPayload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : [];
    }

    private function authResponse(User $user, int $statusCode = JsonResponse::HTTP_OK): JsonResponse
    {
        $boutiques = array_map(static fn (Boutique $boutique): array => [
            'id' => (string) $boutique->getId(),
            'name' => $boutique->getName(),
            'slug' => $boutique->getSlug(),
            'status' => $boutique->getStatus()->value,
        ], $user->getAdministeredBoutiques()->toArray());

        return new JsonResponse([
            'accessToken' => $this->tokens->create($user),
            'user' => [
                'email' => $user->getUserIdentifier(),
                'displayName' => $user->getDisplayName(),
                'firstname' => $user->getFirstname(),
                'lastname' => $user->getLastname(),
                'phone' => $user->getPhone(),
                'status' => $user->getStatus()->value,
                'roles' => $user->getRoles(),
                'boutiques' => $boutiques,
            ],
        ], $statusCode);
    }

    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $tokenUser = $this->security->getUser();

        if (null === $tokenUser) {
            return new JsonResponse(['message' => 'Not authenticated.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $appUser = $this->users->findOneBy(['identifier' => $tokenUser->getUserIdentifier()]);

        $boutiques = [];
        if ($appUser instanceof User) {
            $boutiques = array_map(static fn (Boutique $boutique): array => [
                'id' => (string) $boutique->getId(),
                'name' => $boutique->getName(),
                'slug' => $boutique->getSlug(),
                'status' => $boutique->getStatus()->value,
            ], $appUser->getAdministeredBoutiques()->toArray());
        }

        return new JsonResponse([
            'user' => [
                'email' => $tokenUser->getUserIdentifier(),
                'displayName' => $appUser?->getDisplayName(),
                'firstname' => $appUser?->getFirstname(),
                'lastname' => $appUser?->getLastname(),
                'phone' => $appUser?->getPhone(),
                'status' => $appUser?->getStatus()->value,
                'roles' => $tokenUser->getRoles(),
                'boutiques' => $boutiques,
            ],
        ]);
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    private function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));

        return 'super-admin@market-shop.loca' === $email ? 'super-admin@market-shop.local' : $email;
    }

    private function requiresEmailVerification(User $user): bool
    {
        return in_array('ROLE_BOUTIQUE_ADMIN', $user->getRoles(), true)
            || in_array('ROLE_CAISSIER', $user->getRoles(), true)
            || in_array('ROLE_EMPLOYEE', $user->getRoles(), true);
    }
}
