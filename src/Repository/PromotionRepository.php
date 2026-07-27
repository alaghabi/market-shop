<?php

namespace App\Repository;

use App\Entity\Boutique;
use App\Entity\Promotion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Promotion> */
final class PromotionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Promotion::class);
    }

    /** @return list<Promotion> */
    public function findByBoutique(Boutique $boutique): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.categories', 'pc')->addSelect('pc')
            ->leftJoin('p.products', 'pp')->addSelect('pp')
            ->andWhere('p.boutique = :boutique')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('boutique', $boutique)
            ->orderBy('p.priority', 'DESC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array{items: list<Promotion>, total: int} */
    public function findForBackoffice(?Boutique $boutique, bool $activeOnly, int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('promotion')
            ->andWhere('promotion.deletedAt IS NULL');

        if ($boutique instanceof Boutique) {
            $query->andWhere('promotion.boutique = :boutique')
                ->setParameter('boutique', $boutique);
        }

        if ($activeOnly) {
            $query
                ->andWhere('promotion.active = true')
                ->andWhere('promotion.startsAt <= :now')
                ->andWhere('promotion.endsAt IS NULL OR promotion.endsAt >= :now')
                ->setParameter('now', new \DateTimeImmutable());
        }

        $countQuery = clone $query;
        $total = (int) $countQuery
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(promotion.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $query
            ->orderBy('promotion.priority', 'DESC')
            ->addOrderBy('promotion.name', 'ASC')
            ->addOrderBy('promotion.id', 'ASC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /** @return list<Promotion> */
    public function findActiveByBoutique(Boutique $boutique): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('p')
            ->leftJoin('p.categories', 'pc')->addSelect('pc')
            ->leftJoin('p.products', 'pp')->addSelect('pp')
            ->andWhere('p.boutique = :boutique')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.active = true')
            ->andWhere('p.startsAt <= :now')
            ->andWhere('p.endsAt IS NULL OR p.endsAt >= :now')
            ->setParameter('boutique', $boutique)
            ->setParameter('now', $now)
            ->orderBy('p.priority', 'DESC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
