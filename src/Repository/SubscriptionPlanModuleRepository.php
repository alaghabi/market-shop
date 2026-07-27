<?php

namespace App\Repository;

use App\Entity\SubscriptionPlanModule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SubscriptionPlanModule> */
final class SubscriptionPlanModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubscriptionPlanModule::class);
    }

    public function findOneByCode(string $code): ?SubscriptionPlanModule
    {
        return $this->findOneBy(['code' => $code]);
    }

    /** @return array{items: list<SubscriptionPlanModule>, total: int} */
    public function findForBackoffice(int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('module');
        $countQuery = clone $query;
        $total = (int) $countQuery->resetDQLPart('select')->resetDQLPart('orderBy')
            ->select('COUNT(module.id)')->getQuery()->getSingleScalarResult();
        $items = $query->orderBy('module.category', 'ASC')->addOrderBy('module.name', 'ASC')
            ->addOrderBy('module.id', 'ASC')->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
