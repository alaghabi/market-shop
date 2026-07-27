<?php

namespace App\Repository;

use App\Entity\SubscriptionPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SubscriptionPlan> */
final class SubscriptionPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubscriptionPlan::class);
    }

    /** @return list<SubscriptionPlan> */
    public function findVisibleForBoutique(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.isVisible = :visible')
            ->orderBy('p.priceTnd', 'ASC')
            ->setParameter('active', true)
            ->setParameter('visible', true)
            ->getQuery()
            ->getResult();
    }

    /** @return array{items: list<SubscriptionPlan>, total: int} */
    public function findForBackoffice(bool $visibleOnly, int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('plan');
        if ($visibleOnly) {
            $query->andWhere('plan.isActive = true')->andWhere('plan.isVisible = true');
        }
        $countQuery = clone $query;
        $total = (int) $countQuery->resetDQLPart('select')->resetDQLPart('orderBy')
            ->select('COUNT(plan.id)')->getQuery()->getSingleScalarResult();
        $items = $query->orderBy('plan.priceTnd', 'ASC')->addOrderBy('plan.id', 'ASC')
            ->setFirstResult(($page - 1) * $itemsPerPage)->setMaxResults($itemsPerPage)
            ->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
