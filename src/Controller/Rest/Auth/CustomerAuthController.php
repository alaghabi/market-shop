<?php

namespace App\Controller\Rest\Auth;

use App\Entity\Customer;
use App\Entity\CustomerAuthProvider;
use App\Entity\Boutique;
use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use App\Security\LocalTokenManager;
use App\Service\Subscription\SubscriptionManager;
use App\Service\Module\ModuleAccessService;
use App\Service\Auth\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CustomerAuthController
{
    public function __construct(
        private CustomerRepository $customers,
        private UserRepository $users,
        private EntityManagerInterface $em,
        private LocalTokenManager $tokens,
        private Security $security,
        private SubscriptionManager $subscriptionManager,
        private ModuleAccessService $moduleAccess,
        private EmailVerificationService $emailVerification,
    ) {
    }

    #[Route('/api/boutique/auth/login', name: 'api_customer_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $boutique = $this->resolveBoutique($request);
        $payload = json_decode($request->getContent(), true) ?: [];
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');

        if ('' === $email || '' === $password) {
            return new JsonResponse(['message' => 'Email et mot de passe sont obligatoires.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $customer = $this->customers->findOneBy(['boutique' => $boutique, 'email' => $email, 'deletedAt' => null]);
        if (!$customer instanceof Customer) {
            return new JsonResponse(['message' => 'Identifiants invalides.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $user = $customer->getUser();
        if (!$user || !$user->isPasswordValid($password)) {
            return new JsonResponse(['message' => 'Identifiants invalides.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if ($boutique->isEnableCustomerEmailVerification() && !$user->isEmailVerified()) {
            return new JsonResponse(['message' => 'Veuillez vérifier votre adresse email avant de vous connecter.', 'code' => 'email_verification_required'], JsonResponse::HTTP_FORBIDDEN);
        }

        if (UserStatus::Suspended === $user->getStatus()) {
            return new JsonResponse(['message' => 'Compte suspendu.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $user->markLoggedIn();
        $this->em->flush();

        return $this->customerResponse($customer, $user);
    }

    #[Route('/api/boutique/auth/register', name: 'api_customer_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $boutique = $this->resolveBoutique($request);
        $payload = json_decode($request->getContent(), true) ?: [];
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $firstName = trim((string) ($payload['firstName'] ?? ''));
        $lastName = trim((string) ($payload['lastName'] ?? ''));
        $phone = trim((string) ($payload['phone'] ?? '')) ?: null;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            return new JsonResponse(['message' => 'Email valide et mot de passe 8 caractères minimum.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $existing = $this->customers->findOneBy([
            'boutique' => $boutique,
            'email' => $email,
            'deletedAt' => null,
        ]);
        if ($existing instanceof Customer) {
            return new JsonResponse(['message' => 'Un compte existe déjà pour cet email dans cette boutique.'], JsonResponse::HTTP_CONFLICT);
        }

        if (!$this->subscriptionManager->canCreateCustomer($boutique)) {
            return new JsonResponse(['message' => 'Le quota clients de cette boutique est atteint ou son abonnement est inactif.'], JsonResponse::HTTP_CONFLICT);
        }

        $user = $this->users->findOneBy(['identifier' => $email]);
        if ($user instanceof User && !in_array('ROLE_CUSTOMER', $user->getRoles(), true)) {
            return new JsonResponse(['message' => 'Cet email est déjà utilisé par un compte interne.'], JsonResponse::HTTP_CONFLICT);
        }

        $customer = new Customer(
            boutique: $boutique,
            email: $email,
            firstName: $firstName ?: null,
            lastName: $lastName ?: null,
            phone: $phone,
        );
        $this->em->persist($customer);

        if (!$user instanceof User) {
            $user = new User(
                boutique: null,
                identifier: $email,
                roles: ['ROLE_CUSTOMER'],
            );
            $user->setPassword($password);
            $this->em->persist($user);
        }

        if ($boutique->isEnableCustomerEmailVerification()) {
            if (!$user->isEmailVerified() && UserStatus::Suspended !== $user->getStatus()) {
                $user->setStatus(UserStatus::Pending);
            }
        } else {
            $user->markEmailVerified();
            if (UserStatus::Suspended !== $user->getStatus()) {
                $user->setStatus(UserStatus::Active);
            }
        }

        if (null === $user->getFirstname() && '' !== $firstName) {
            $user->setFirstname($firstName);
        }
        if (null === $user->getLastname() && '' !== $lastName) {
            $user->setLastname($lastName);
        }
        if (null === $user->getPhone() && null !== $phone) {
            $user->setPhone($phone);
        }

        $customer->setUser($user);
        $this->em->flush();

        if ($boutique->isEnableCustomerEmailVerification()) {
            $this->emailVerification->issue($user, $boutique);
        }

        if ($boutique->isEnableCustomerEmailVerification()) {
            return new JsonResponse([
                'message' => 'Compte créé. Vérifiez votre email pour activer votre compte client.',
                'verificationRequired' => true,
            ], JsonResponse::HTTP_CREATED);
        }

        return $this->customerResponse($customer, $user, JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/boutique/auth/social/status', name: 'api_customer_social_status', methods: ['GET'])]
    public function socialStatus(Request $request): JsonResponse
    {
        $boutique = $this->resolveBoutique($request);
        $enabled = $this->moduleAccess->isModuleEnabled('social_login', $boutique);

        return new JsonResponse(['enabled' => $enabled]);
    }

    #[Route('/api/boutique/auth/social', name: 'api_customer_social_login', methods: ['POST'])]
    public function socialLogin(Request $request): JsonResponse
    {
        $boutique = $this->resolveBoutique($request);
        if (!$this->moduleAccess->isModuleEnabled('social_login', $boutique)) {
            throw new NotFoundHttpException('Social login is not enabled for this boutique.');
        }

        $tokenUser = $this->security->getUser();
        if (!$tokenUser instanceof User) {
            return new JsonResponse(['message' => 'Un token Keycloak valide est requis.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if (!in_array('ROLE_CUSTOMER', $tokenUser->getRoles(), true)) {
            return new JsonResponse(['message' => 'Ce compte ne peut pas être utilisé comme compte client.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $customerEmail = strtolower($tokenUser->getUserIdentifier());
        $provider = 'keycloak';
        $providerUserId = $tokenUser->getKeycloakSubject();
        if (null === $providerUserId || '' === $providerUserId) {
            return new JsonResponse(['message' => 'Identité Keycloak incomplète.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $customer = $this->customers->findOneBy([
            'boutique' => $boutique,
            'user' => $tokenUser,
            'deletedAt' => null,
        ]);
        if ($customer instanceof Customer) {
            return $this->customerResponse($customer, $tokenUser);
        }

        $customer = $this->customers->findOneBy([
            'boutique' => $boutique,
            'email' => $customerEmail,
            'deletedAt' => null,
        ]);
        if ($customer instanceof Customer) {
            if (null !== $customer->getUser() && $customer->getUser() !== $tokenUser) {
                return new JsonResponse(['message' => 'Cet email est déjà rattaché à un autre compte client.'], JsonResponse::HTTP_CONFLICT);
            }

            $customer->setUser($tokenUser);
            $this->em->flush();

            return $this->customerResponse($customer, $tokenUser);
        }

        if (!$this->subscriptionManager->canCreateCustomer($boutique)) {
            return new JsonResponse(['message' => 'Le quota clients de cette boutique est atteint ou son abonnement est inactif.'], JsonResponse::HTTP_CONFLICT);
        }

        $customer = new Customer(
            boutique: $boutique,
            email: $customerEmail,
            firstName: $tokenUser->getFirstname(),
            lastName: $tokenUser->getLastname(),
        );
        $this->em->persist($customer);

        $authRecord = new CustomerAuthProvider($customer, $provider, $providerUserId);
        $this->em->persist($authRecord);
        $this->em->flush();

        return $this->customerResponse($customer, $tokenUser, JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/boutique/auth/me', name: 'api_customer_me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        $boutique = $this->resolveBoutique($request);
        $tokenUser = $this->security->getUser();

        if (null === $tokenUser) {
            return new JsonResponse(['message' => 'Not authenticated.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $appUser = $this->users->findOneBy(['identifier' => $tokenUser->getUserIdentifier()]);
        if (!$appUser instanceof User) {
            return new JsonResponse(['message' => 'Not authenticated.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $customer = $this->customers->findOneBy([
            'boutique' => $boutique,
            'user' => $appUser,
            'deletedAt' => null,
        ]);

        if (!$customer instanceof Customer) {
            return new JsonResponse(['message' => 'No customer account found for this boutique.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'customer' => [
                'id' => (string) $customer->getId(),
                'email' => $customer->getEmail(),
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'phone' => $customer->getPhone(),
                'boutique' => [
                    'id' => (string) $boutique->getId(),
                    'name' => $boutique->getName(),
                    'slug' => $boutique->getSlug(),
                ],
            ],
        ]);
    }

    private function customerResponse(Customer $customer, ?User $user, int $statusCode = JsonResponse::HTTP_OK): JsonResponse
    {
        $boutique = $customer->getBoutique();

        return new JsonResponse([
            'accessToken' => $user && null === $user->getKeycloakSubject() ? $this->tokens->create($user) : null,
            'customer' => [
                'id' => (string) $customer->getId(),
                'email' => $customer->getEmail(),
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'phone' => $customer->getPhone(),
                'boutique' => [
                    'id' => (string) $boutique->getId(),
                    'name' => $boutique->getName(),
                    'slug' => $boutique->getSlug(),
                ],
            ],
        ], $statusCode);
    }

    private function resolveBoutique(Request $request): Boutique
    {
        $boutique = $request->attributes->get('_boutique');
        if (!$boutique instanceof Boutique) {
            throw new NotFoundHttpException('Boutique not found.');
        }

        $moduleConfig = $boutique->getSettings()?->getModuleConfig() ?? [];
        if (!$this->moduleAccess->isModuleEnabled('customer_auth', $boutique)
            || (array_key_exists('enable_customer_auth', $moduleConfig) && false === (bool) $moduleConfig['enable_customer_auth'])) {
            throw new NotFoundHttpException('Customer accounts are not enabled for this boutique.');
        }

        return $boutique;
    }
}
