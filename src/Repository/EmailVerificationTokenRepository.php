<?php

namespace App\Repository;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<EmailVerificationToken> */
final class EmailVerificationTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailVerificationToken::class);
    }

    public function findUsableByPlainToken(string $plainToken): ?EmailVerificationToken
    {
        $token = $this->findOneBy(['tokenHash' => hash('sha256', $plainToken)]);

        return $token instanceof EmailVerificationToken && $token->isUsable(new \DateTimeImmutable()) ? $token : null;
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
