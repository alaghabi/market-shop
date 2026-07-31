<?php

namespace App\Repository;

use App\Entity\AccountSubscription;
use App\Entity\AccountSubscriptionExtension;
use App\Entity\Extension;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AccountSubscriptionExtension> */
final class AccountSubscriptionExtensionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountSubscriptionExtension::class);
    }

    /** @return AccountSubscriptionExtension[] */
    public function findActiveByAccountSubscription(AccountSubscription $accountSubscription): array
    {
        return $this->findBy(['accountSubscription' => $accountSubscription, 'isActive' => true]);
    }

    public function findOneActiveByAccountSubscriptionAndExtension(AccountSubscription $accountSubscription, Extension $extension): ?AccountSubscriptionExtension
    {
        return $this->findOneBy(['accountSubscription' => $accountSubscription, 'extension' => $extension, 'isActive' => true]);
    }

    /** @return AccountSubscriptionExtension[] */
    public function findByAccountSubscription(AccountSubscription $accountSubscription): array
    {
        return $this->findBy(['accountSubscription' => $accountSubscription], ['activatedAt' => 'ASC']);
    }

    /** @return AccountSubscriptionExtension[] active grants that have an expiresAt in the past */
    public function findExpiredButStillActive(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('ase')
            ->andWhere('ase.isActive = :active')
            ->andWhere('ase.expiresAt IS NOT NULL')
            ->andWhere('ase.expiresAt <= :now')
            ->setParameter('active', true)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /** @return AccountSubscriptionExtension[] active grants expiring within the given window, not yet notified */
    public function findExpiringSoon(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('ase')
            ->andWhere('ase.isActive = :active')
            ->andWhere('ase.expiresAt IS NOT NULL')
            ->andWhere('ase.expiresAt > :from')
            ->andWhere('ase.expiresAt <= :to')
            ->andWhere('ase.expiryNotifiedAt IS NULL')
            ->setParameter('active', true)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }
}
