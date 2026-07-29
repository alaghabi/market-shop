<?php

namespace App\Entity;

use App\Repository\EmailVerificationTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailVerificationTokenRepository::class)]
#[ORM\Table(name: 'email_verification_token')]
#[ORM\Index(columns: ['user_id', 'expires_at'], name: 'idx_email_verification_user_expiry')]
class EmailVerificationToken extends AbstractEntity
{
    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\ManyToOne(targetEntity: Boutique::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        private ?Boutique $boutique,
        #[ORM\Column(length: 64, unique: true)]
        private string $tokenHash,
        #[ORM\Column]
        private \DateTimeImmutable $expiresAt,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $usedAt = null,
    ) {
        parent::__construct();
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getBoutique(): ?Boutique
    {
        return $this->boutique;
    }

    public function isUsable(\DateTimeImmutable $now): bool
    {
        return null === $this->usedAt && $this->expiresAt > $now;
    }

    public function markUsed(): void
    {
        $this->usedAt = new \DateTimeImmutable();
    }
}
