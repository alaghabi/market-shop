<?php

namespace App\Repository;

use App\Entity\Boutique;
use App\Entity\Subscription;
use App\Enum\SubscriptionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Subscription> */
final class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    /** @return list<Subscription> */
    public function findActiveExpiringBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->andWhere('s.endDate IS NOT NULL')
            ->andWhere('s.endDate >= :from')
            ->andWhere('s.endDate <= :to')
            ->setParameter('status', SubscriptionStatus::Active)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    /** @return array{items: list<Subscription>, total: int} */
    public function findByBoutiquePaginated(Boutique $boutique, int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('subscription')
            ->andWhere('subscription.boutique = :boutique')
            ->setParameter('boutique', $boutique);

        $countQuery = clone $query;
        $total = (int) $countQuery
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(subscription.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $query
            ->orderBy('subscription.createdAt', 'DESC')
            ->addOrderBy('subscription.id', 'DESC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /** @return list<Subscription> */
    public function findActiveExpired(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->andWhere('s.endDate IS NOT NULL')
            ->andWhere('s.endDate <= :now')
            ->setParameter('status', SubscriptionStatus::Active)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(SubscriptionStatus $status): int
    {
        return $this->count(['status' => $status]);
    }

    /**
     * Approximate recurring revenue: sum of the plan price for every currently active subscription.
     */
    public function sumActiveRevenue(): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('SUM(p.priceTnd) AS total')
            ->join('s.subscriptionPlan', 'p')
            ->andWhere('s.status = :status')
            ->setParameter('status', SubscriptionStatus::Active);

        $result = $qb->getQuery()->getSingleScalarResult();

        return null === $result ? 0 : (int) $result;
    }
}
