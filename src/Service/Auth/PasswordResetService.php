<?php

namespace App\Service\Auth;

use App\Entity\Boutique;
use App\Entity\Customer;
use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Enum\NotificationChannel;
use App\Repository\CustomerRepository;
use App\Repository\PasswordResetTokenRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PasswordResetService
{
    private const TOKEN_TTL = 3600;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PasswordResetTokenRepository $tokens,
        private CustomerRepository $customers,
        private NotificationService $notifications,
    ) {
    }

    public function issue(User $user, string $resetUrl, ?Boutique $boutique = null): void
    {
        $this->tokens->invalidateForUser($user);
        $plainToken = bin2hex(random_bytes(32));
        $token = new PasswordResetToken(
            $user,
            hash('sha256', $plainToken),
            new \DateTimeImmutable(sprintf('+%d seconds', self::TOKEN_TTL)),
        );
        $this->entityManager->persist($token);
        $this->entityManager->flush();

        $this->notifications->dispatchExternal(
            $boutique,
            'password_reset',
            NotificationChannel::Email,
            $user->getUserIdentifier(),
            [
                'name' => $user->getDisplayName() ?: $user->getFirstname() ?: 'Utilisateur',
                'resetUrl' => $resetUrl.'?token='.urlencode($plainToken),
            ],
            null,
            'password-reset:'.$token->getId(),
        );
    }

    public function reset(string $plainToken, string $password, ?Boutique $boutique = null): bool
    {
        $token = $this->tokens->findUsableByPlainToken($plainToken);
        if (!$token instanceof PasswordResetToken) {
            return false;
        }

        $user = $token->getUser();
        if ($boutique instanceof Boutique && !$this->customers->findOneBy(['boutique' => $boutique, 'user' => $user, 'deletedAt' => null]) instanceof Customer) {
            return false;
        }

        $user->setPassword($password);
        $token->markUsed();
        $this->entityManager->flush();

        return true;
    }
}
