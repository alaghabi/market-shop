<?php

namespace App\Service\Auth;

use App\Entity\Boutique;
use App\Entity\EmailVerificationToken;
use App\Entity\User;
use App\Enum\NotificationChannel;
use App\Enum\UserStatus;
use App\Repository\EmailVerificationTokenRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;

final readonly class EmailVerificationService
{
    private const TOKEN_TTL = 86400;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailVerificationTokenRepository $tokens,
        private NotificationService $notifications,
        private string $publicBaseUrl,
    ) {
    }

    public function issue(User $user, ?Boutique $boutique = null): void
    {
        $this->tokens->invalidateForUser($user);
        $plainToken = bin2hex(random_bytes(32));
        $token = new EmailVerificationToken(
            user: $user,
            boutique: $boutique,
            tokenHash: hash('sha256', $plainToken),
            expiresAt: (new \DateTimeImmutable())->modify(sprintf('+%d seconds', self::TOKEN_TTL)),
            createdAt: new \DateTimeImmutable(),
        );
        $this->entityManager->persist($token);
        $this->entityManager->flush();

        $this->notifications->dispatchExternal(
            $boutique,
            'email_verification',
            NotificationChannel::Email,
            $user->getUserIdentifier(),
            [
                'name' => $user->getDisplayName() ?: $user->getFirstname() ?: 'Utilisateur',
                'verificationUrl' => rtrim($this->publicBaseUrl, '/').'/auth/verify-email?token='.urlencode($plainToken),
            ],
            null,
            'email-verification:'.$token->getId(),
        );
    }

    public function verify(string $plainToken): ?User
    {
        $token = $this->tokens->findUsableByPlainToken($plainToken);
        if (!$token instanceof EmailVerificationToken) {
            return null;
        }

        $user = $token->getUser();
        $user->markEmailVerified();
        if (UserStatus::Pending === $user->getStatus()) {
            $user->setStatus(UserStatus::Active);
        }

        foreach ($user->getUserShops() as $userShop) {
            if (UserStatus::Pending === $userShop->getStatus()) {
                $userShop->setStatus(UserStatus::Active);
            }
        }

        $token->markUsed();
        $this->entityManager->flush();

        return $user;
    }
}
