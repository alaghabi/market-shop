<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductFavorite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ProductFavorite> */
final class ProductFavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductFavorite::class);
    }

    /** @return list<ProductFavorite> */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    /** @return list<ProductFavorite> */
    public function findBySession(string $sessionId): array
    {
        return $this->findBy(['sessionId' => $sessionId], ['createdAt' => 'DESC']);
    }

    public function findOneByUserAndProduct(User $user, Product $product): ?ProductFavorite
    {
        return $this->findOneBy(['user' => $user, 'product' => $product, 'boutique' => $product->getBoutique()]);
    }

    public function findOneBySessionAndProduct(string $sessionId, Product $product): ?ProductFavorite
    {
        return $this->findOneBy(['sessionId' => $sessionId, 'product' => $product, 'boutique' => $product->getBoutique()]);
    }

    public function countByProduct(Product $product): int
    {
        return (int) $this->createQueryBuilder('favorite')
            ->select('COUNT(favorite.id)')
            ->andWhere('favorite.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deleteBySession(string $sessionId): void
    {
        $this->createQueryBuilder('f')
            ->delete()
            ->andWhere('f.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->execute();
    }
}
