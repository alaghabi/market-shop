<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Boutique;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Product> */
final class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findBySlugOrId(string $identifier, ?Boutique $boutique = null): ?Product
    {
        if (null === $boutique) {
            return Uuid::isValid($identifier)
                ? $this->find($identifier)
                : null;
        }

        if (Uuid::isValid($identifier)) {
            $product = $this->find($identifier);
            if ($product && (string) $product->getBoutique()->getId() === (string) $boutique->getId()) {
                return $product;
            }

            return null;
        }

        return $this->findOneBy(['boutique' => $boutique, 'slug' => $identifier]);
    }

    /** @return array{items: list<Product>, total: int} */
    public function findForBackoffice(?Boutique $boutique, int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('product')
            ->andWhere('product.deletedAt IS NULL');

        if ($boutique instanceof Boutique) {
            $query->andWhere('product.boutique = :boutique')
                ->setParameter('boutique', $boutique);
        }

        $countQuery = clone $query;
        $total = (int) $countQuery
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(product.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $query
            ->orderBy('product.name', 'ASC')
            ->addOrderBy('product.id', 'ASC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /** @return list<array{id: string, name: string, slug: string, boutiqueId: string, boutiqueName: string, viewsCount: int}> */
    public function findViewStats(?Boutique $boutique = null): array
    {
        $queryBuilder = $this->createQueryBuilder('product')
            ->select(
                'product.id AS id',
                'product.name AS name',
                'product.slug AS slug',
                'product.viewsCount AS viewsCount',
                'boutique.id AS boutiqueId',
                'boutique.name AS boutiqueName',
            )
            ->join('product.boutique', 'boutique')
            ->andWhere('product.deletedAt IS NULL')
            ->orderBy('product.viewsCount', 'DESC')
            ->addOrderBy('product.name', 'ASC');

        if ($boutique instanceof Boutique) {
            $queryBuilder
                ->andWhere('product.boutique = :boutique')
                ->setParameter('boutique', $boutique);
        }

        return array_map(
            static fn (array $row): array => [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'boutiqueId' => (string) $row['boutiqueId'],
                'boutiqueName' => (string) $row['boutiqueName'],
                'viewsCount' => (int) $row['viewsCount'],
            ],
            $queryBuilder->getQuery()->getArrayResult(),
        );
    }

    /** @return list<Product> */
    public function findSeoIndexedByBoutique(Boutique $boutique): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.boutique = :boutique')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.status = :status')
            ->setParameter('boutique', $boutique)
            ->setParameter('status', \App\Enum\ProductStatus::Active)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countSeoIndexedByBoutique(Boutique $boutique): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.boutique = :boutique')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.status = :status')
            ->setParameter('boutique', $boutique)
            ->setParameter('status', \App\Enum\ProductStatus::Active)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
