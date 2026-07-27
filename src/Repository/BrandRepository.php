<?php

namespace App\Repository;

use App\Entity\Boutique;
use App\Entity\Brand;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Brand> */
final class BrandRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Brand::class);
    }

    /** @return Brand[] */
    public function findByBoutique(Boutique $boutique): array
    {
        return $this->findBy(['boutique' => $boutique, 'deletedAt' => null], ['name' => 'ASC']);
    }

    /** @return array{items: list<Brand>, total: int} */
    public function findForBackoffice(?Boutique $boutique, int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('brand')
            ->andWhere('brand.deletedAt IS NULL');

        if ($boutique instanceof Boutique) {
            $query->andWhere('brand.boutique = :boutique')
                ->setParameter('boutique', $boutique);
        }

        $countQuery = clone $query;
        $total = (int) $countQuery
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(brand.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $query
            ->orderBy('brand.name', 'ASC')
            ->addOrderBy('brand.id', 'ASC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
