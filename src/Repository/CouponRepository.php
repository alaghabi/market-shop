<?php

namespace App\Repository;

use App\Entity\Coupon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Coupon> */
final class CouponRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Coupon::class);
    }

    public function findActiveByCode(string $boutiqueId, string $code): ?Coupon
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.boutique = :boutiqueId')
            ->andWhere('UPPER(c.code) = UPPER(:code)')
            ->andWhere('c.isActive = 1')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('boutiqueId', $boutiqueId)
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Coupon> */
    public function findByBoutique(string $boutiqueId, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.boutique = :boutiqueId')
            ->andWhere('c.deletedAt IS NULL')
            ->orderBy('c.createdAt', 'DESC')
            ->setParameter('boutiqueId', $boutiqueId)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /** @return array{items: list<Coupon>, total: int} */
    public function findForBackoffice(string $boutiqueId, int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('coupon')
            ->andWhere('coupon.boutique = :boutiqueId')
            ->andWhere('coupon.deletedAt IS NULL')
            ->setParameter('boutiqueId', $boutiqueId);

        $countQuery = clone $query;
        $total = (int) $countQuery
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(coupon.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $query
            ->orderBy('coupon.createdAt', 'DESC')
            ->addOrderBy('coupon.id', 'DESC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
