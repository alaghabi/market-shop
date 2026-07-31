<?php

namespace App\Repository;

use App\Entity\AccountSubscription;
use App\Entity\User;
use App\Enum\AccountSubscriptionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AccountSubscription> */
final class AccountSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountSubscription::class);
    }

    public function findActiveByUser(User $user): ?AccountSubscription
    {
        return $this->findOneBy(['user' => $user, 'status' => AccountSubscriptionStatus::Active]);
    }

    /** @return AccountSubscription[] */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    /** @return AccountSubscription[] */
    public function findExpiredButStillActive(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('sub')
            ->andWhere('sub.status = :status')
            ->andWhere('sub.endDate IS NOT NULL')
            ->andWhere('sub.endDate <= :now')
            ->setParameter('status', AccountSubscriptionStatus::Active)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{items: AccountSubscription[], total: int}
     */
    public function findAllPaginated(int $page, int $itemsPerPage): array
    {
        $total = (int) $this->createQueryBuilder('sub')
            ->select('COUNT(sub.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $this->createQueryBuilder('sub')
            ->leftJoin('sub.user', 'u')->addSelect('u')
            ->leftJoin('sub.subscriptionPlan', 'plan')->addSelect('plan')
            ->orderBy('sub.createdAt', 'DESC')
            ->setFirstResult(max(0, ($page - 1) * $itemsPerPage))
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
