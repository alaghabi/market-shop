<?php

namespace App\Repository;

use App\Entity\ProductFilter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ProductFilter> */
final class ProductFilterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductFilter::class);
    }

    /** @return array<ProductFilter> */
    public function findActiveByBoutique(string $boutiqueId): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.boutique = :boutiqueId')
            ->andWhere('f.active = :active')
            ->setParameter('boutiqueId', $boutiqueId)
            ->setParameter('active', true)
            ->orderBy('f.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array{items: list<ProductFilter>, total: int} */
    public function findForBackoffice(?string $boutiqueId, bool $activeOnly, int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('filter');

        if (null !== $boutiqueId) {
            $query->andWhere('filter.boutique = :boutiqueId')
                ->setParameter('boutiqueId', $boutiqueId);
        }

        if ($activeOnly) {
            $query->andWhere('filter.active = true');
        }

        $countQuery = clone $query;
        $total = (int) $countQuery
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(filter.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $query
            ->orderBy('filter.position', 'ASC')
            ->addOrderBy('filter.name', 'ASC')
            ->addOrderBy('filter.id', 'ASC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
