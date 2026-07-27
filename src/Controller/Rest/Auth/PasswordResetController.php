<?php

namespace App\Controller\Rest\Auth;

use App\Entity\Boutique;
use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use App\Service\Auth\PasswordResetService;
use App\Service\Module\ModuleAccessService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class PasswordResetController
{
    public function __construct(
        private UserRepository $users,
        private CustomerRepository $customers,
        private PasswordResetService $passwordResets,
        private ModuleAccessService $moduleAccess,
    ) {
    }

    #[Route('/api/auth/password-reset/request', name: 'api_auth_password_reset_request', methods: ['POST'])]
    public function requestGlobal(Request $request): JsonResponse
    {
        $email = $this->email($request);
        $user = '' !== $email ? $this->users->findOneBy(['identifier' => $email]) : null;
        if ($user instanceof User) {
            $this->passwordResets->issue($user, $request->getSchemeAndHttpHost().'/auth/reset-password');
        }

        return $this->accepted();
    }

    #[Route('/api/boutique/auth/password-reset/request', name: 'api_customer_password_reset_request', methods: ['POST'])]
    #[Route('/api/boutique/auth/password-reset', name: 'api_customer_password_reset_request_alias', methods: ['POST'])]
    public function requestCustomer(Request $request): JsonResponse
    {
        $boutique = $this->boutique($request);
        $email = $this->email($request);
        $customer = '' !== $email ? $this->customers->findOneBy(['boutique' => $boutique, 'email' => $email, 'deletedAt' => null]) : null;
        $user = $customer?->getUser();
        if ($user instanceof User) {
            $this->passwordResets->issue($user, $request->getSchemeAndHttpHost().'/client/reset-password', $boutique);
        }

        return $this->accepted();
    }

    #[Route('/api/auth/password-reset/reset', name: 'api_auth_password_reset', methods: ['POST'])]
    public function resetGlobal(Request $request): JsonResponse
    {
        return $this->reset($request);
    }

    #[Route('/api/boutique/auth/password-reset/reset', name: 'api_customer_password_reset', methods: ['POST'])]
    public function resetCustomer(Request $request): JsonResponse
    {
        return $this->reset($request, $this->boutique($request));
    }

    private function reset(Request $request, ?Boutique $boutique = null): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $payload = is_array($payload) ? $payload : [];
        $token = trim((string) ($payload['token'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        if ('' === $token || strlen($password) < 8 || !$this->passwordResets->reset($token, $password, $boutique)) {
            return new JsonResponse(['message' => 'Le lien est invalide ou expiré.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['message' => 'Mot de passe mis à jour.']);
    }

    private function boutique(Request $request): Boutique
    {
        $boutique = $request->attributes->get('_boutique');
        if (!$boutique instanceof Boutique || !$this->moduleAccess->isModuleEnabled('customer_auth', $boutique)) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Boutique introuvable.');
        }

        return $boutique;
    }

    private function email(Request $request): string
    {
        $payload = json_decode($request->getContent(), true);
        $payload = is_array($payload) ? $payload : [];
        $email = strtolower(trim((string) ($payload['email'] ?? '')));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private function accepted(): JsonResponse
    {
        return new JsonResponse(['message' => 'Si un compte existe pour cet email, un lien de réinitialisation sera envoyé.'], JsonResponse::HTTP_ACCEPTED);
    }
}
