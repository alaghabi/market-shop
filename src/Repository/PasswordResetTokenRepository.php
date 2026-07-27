<?php

namespace App\Repository;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PasswordResetToken> */
final class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function findUsableByPlainToken(string $plainToken): ?PasswordResetToken
    {
        $token = $this->findOneBy(['tokenHash' => hash('sha256', $plainToken)]);

        return $token instanceof PasswordResetToken && $token->isUsable(new \DateTimeImmutable()) ? $token : null;
    }

    public function invalidateForUser(User $user): void
    {
        $this->createQueryBuilder('token')
            ->update()
            ->set('token.usedAt', ':now')
            ->andWhere('token.user = :user')
            ->andWhere('token.usedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
