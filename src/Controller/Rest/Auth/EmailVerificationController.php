<?php

namespace App\Controller\Rest\Auth;

use App\Entity\Boutique;
use App\Entity\Customer;
use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use App\Service\Auth\EmailVerificationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class EmailVerificationController
{
    public function __construct(private EmailVerificationService $verification)
    {
    }

    #[Route('/api/auth/verify-email', name: 'api_auth_verify_email', methods: ['GET'])]
    public function verify(Request $request): JsonResponse
    {
        $token = trim((string) $request->query->get('token', ''));
        if ('' === $token || !$this->verification->verify($token)) {
            return new JsonResponse(['message' => 'Le lien de vérification est invalide ou expiré.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['message' => 'Votre adresse email a été vérifiée. Vous pouvez maintenant vous connecter.']);
    }

    #[Route('/api/auth/resend-email-verification', name: 'api_auth_resend_email_verification', methods: ['POST'])]
    public function resendGlobal(Request $request, UserRepository $users): JsonResponse
    {
        $email = $this->email($request);
        $user = '' !== $email ? $users->findOneBy(['identifier' => $email]) : null;
        if ($user instanceof User && !$user->isEmailVerified() && $this->isInternalAccount($user)) {
            $this->verification->issue($user, $user->getBoutique());
        }

        return new JsonResponse(['message' => 'Si un compte interne non vérifié existe, un email de vérification sera envoyé.'], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route('/api/boutique/auth/resend-email-verification', name: 'api_customer_resend_email_verification', methods: ['POST'])]
    public function resendCustomer(Request $request, CustomerRepository $customers): JsonResponse
    {
        $boutique = $request->attributes->get('_boutique');
        $email = $this->email($request);
        $customer = $boutique instanceof Boutique && '' !== $email
            ? $customers->findOneBy(['boutique' => $boutique, 'email' => $email, 'deletedAt' => null])
            : null;
        $user = $customer instanceof Customer ? $customer->getUser() : null;
        if ($boutique instanceof Boutique && $user instanceof User && $boutique->isEnableCustomerEmailVerification() && !$user->isEmailVerified()) {
            $this->verification->issue($user, $boutique);
        }

        return new JsonResponse(['message' => 'Si un compte client non vérifié existe, un email de vérification sera envoyé.'], JsonResponse::HTTP_ACCEPTED);
    }

    private function email(Request $request): string
    {
        $payload = json_decode($request->getContent(), true);
        $email = is_array($payload) ? strtolower(trim((string) ($payload['email'] ?? ''))) : '';

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private function isInternalAccount(User $user): bool
    {
        return in_array('ROLE_BOUTIQUE_ADMIN', $user->getRoles(), true)
            || in_array('ROLE_CAISSIER', $user->getRoles(), true)
            || in_array('ROLE_EMPLOYEE', $user->getRoles(), true);
    }
}
