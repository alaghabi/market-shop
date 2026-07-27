<?php

namespace App\Controller\Rest\Auth;

use App\Entity\Customer;
use App\Entity\CustomerAuthProvider;
use App\Entity\Boutique;
use App\Enum\UserStatus;
use App\Repository\CustomerRepository;
use App\Repository\CustomerAuthProviderRepository;
use App\Security\LocalTokenManager;
use App\Service\Subscription\SubscriptionManager;
use App\Service\Module\ModuleAccessService;
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
        private CustomerAuthProviderRepository $authProviders,
        private EntityManagerInterface $em,
        private LocalTokenManager $tokens,
        private Security $security,
        private SubscriptionManager $subscriptionManager,
        private ModuleAccessService $moduleAccess,
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

        $customer = new Customer(
            boutique: $boutique,
            email: $email,
            firstName: $firstName ?: null,
            lastName: $lastName ?: null,
            phone: $phone,
        );
        $this->em->persist($customer);

        $user = new \App\Entity\User(
            boutique: null,
            identifier: $email,
            roles: ['ROLE_CUSTOMER'],
        );
        $user->setPassword($password);
        $user->setStatus(UserStatus::Active);
        $user->setFirstname($firstName ?: null);
        $user->setLastname($lastName ?: null);
        $user->setPhone($phone);
        $this->em->persist($user);

        $customer->setUser($user);
        $this->em->flush();

        return $this->customerResponse($customer, $user, JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/boutique/auth/social', name: 'api_customer_social_login', methods: ['POST'])]
    public function socialLogin(Request $request): JsonResponse
    {
        $boutique = $this->resolveBoutique($request);
        $payload = json_decode($request->getContent(), true) ?: [];
        $provider = strtolower(trim((string) ($payload['provider'] ?? '')));
        $providerUserId = trim((string) ($payload['providerUserId'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $displayName = trim((string) ($payload['displayName'] ?? '')) ?: null;
        $firstName = trim((string) ($payload['firstName'] ?? '')) ?: null;
        $lastName = trim((string) ($payload['lastName'] ?? '')) ?: null;

        $allowedProviders = ['google', 'facebook', 'apple'];
        if (!in_array($provider, $allowedProviders, true) || '' === $providerUserId) {
            return new JsonResponse(['message' => 'Fournisseur et providerUserId sont obligatoires.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $existingAuth = $this->authProviders->findOneBy([
            'provider' => $provider,
            'providerUserId' => $providerUserId,
        ]);
        if ($existingAuth instanceof CustomerAuthProvider) {
            $existingCustomer = $existingAuth->getCustomer();
            if ((string) $existingCustomer->getBoutique()->getId() === (string) $boutique->getId()) {
                $user = $existingCustomer->getUser();
                $this->em->flush();

                return $this->customerResponse($existingCustomer, $user);
            }
        }

        if ('' !== $email) {
            $customer = $this->customers->findOneBy([
                'boutique' => $boutique,
                'email' => $email,
                'deletedAt' => null,
            ]);
            if ($customer instanceof Customer) {
                $existingProviderLink = $this->authProviders->findOneBy([
                    'customer' => $customer,
                    'provider' => $provider,
                ]);
                if (!$existingProviderLink instanceof CustomerAuthProvider) {
                    $auth = new CustomerAuthProvider($customer, $provider, $providerUserId);
                    $this->em->persist($auth);
                }
                $user = $customer->getUser();
                $this->em->flush();

                return $this->customerResponse($customer, $user);
            }
        }

        $customerEmail = $email ?: sprintf('%s_%s@social.local', $provider, substr($providerUserId, 0, 16));

        if (!$this->subscriptionManager->canCreateCustomer($boutique)) {
            return new JsonResponse(['message' => 'Le quota clients de cette boutique est atteint ou son abonnement est inactif.'], JsonResponse::HTTP_CONFLICT);
        }

        $customer = new Customer(
            boutique: $boutique,
            email: $customerEmail,
            firstName: $firstName,
            lastName: $lastName,
        );
        $this->em->persist($customer);

        $user = new \App\Entity\User(
            boutique: null,
            identifier: $customerEmail,
            roles: ['ROLE_CUSTOMER'],
            displayName: $displayName,
        );
        $user->setStatus(UserStatus::Active);
        $this->em->persist($user);
        $customer->setUser($user);

        $authRecord = new CustomerAuthProvider($customer, $provider, $providerUserId);
        $this->em->persist($authRecord);
        $this->em->flush();

        return $this->customerResponse($customer, $user, JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/boutique/auth/me', name: 'api_customer_me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        $boutique = $this->resolveBoutique($request);
        $tokenUser = $this->security->getUser();

        if (null === $tokenUser) {
            return new JsonResponse(['message' => 'Not authenticated.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $appUser = $this->security->getUser();
        if (!$appUser instanceof \App\Entity\User) {
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

    private function customerResponse(Customer $customer, ?\App\Entity\User $user, int $statusCode = JsonResponse::HTTP_OK): JsonResponse
    {
        $boutique = $customer->getBoutique();

        return new JsonResponse([
            'accessToken' => $user ? $this->tokens->create($user) : null,
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
