<?php

namespace App\Repository;

use App\Entity\Boutique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Boutique> */
final class BoutiqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Boutique::class);
    }

    public function findBySlug(string $slug): ?Boutique
    {
        return $this->findOneBy(['slug' => $slug, 'deletedAt' => null]);
    }

    public function findBySlugOrId(string $identifier): ?Boutique
    {
        if (Uuid::isValid($identifier)) {
            return $this->findOneBy(['id' => $identifier, 'deletedAt' => null]);
        }

        return $this->findBySlug($identifier);
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return list<Boutique>
     */
    public function findVisibleTo(array $ids, bool $isSuperAdmin): array
    {
        if ($isSuperAdmin) {
            return $this->findBy(['deletedAt' => null], ['createdAt' => 'DESC']);
        }

        if ([] === $ids) {
            return [];
        }

        return $this->createQueryBuilder('boutique')
            ->andWhere('boutique.id IN (:ids)')
            ->andWhere('boutique.deletedAt IS NULL')
            ->setParameter('ids', $ids)
            ->orderBy('boutique.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @param list<Uuid> $ids @return array{items: list<Boutique>, total: int} */
    public function findVisibleToPaginated(array $ids, bool $isSuperAdmin, int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('boutique');

        if (!$isSuperAdmin) {
            if ([] === $ids) {
                return ['items' => [], 'total' => 0];
            }

            $query->andWhere('boutique.id IN (:ids)')
                ->setParameter('ids', $ids);
        }

        $query->andWhere('boutique.deletedAt IS NULL');

        return $this->paginateBoutiques($query, $page, $itemsPerPage);
    }

    /** @return list<Boutique> */
    public function findPublishedForPublic(): array
    {
        return $this->createQueryBuilder('boutique')
            ->innerJoin('boutique.subscriptions', 'subscription')
            ->distinct()
            ->andWhere('boutique.deletedAt IS NULL')
            ->andWhere('boutique.status = :status')
            ->andWhere('boutique.isPublished = :published')
            ->andWhere('subscription.status = :subStatus')
            ->setParameter('status', \App\Enum\BoutiqueStatus::Active)
            ->setParameter('published', true)
            ->setParameter('subStatus', \App\Enum\SubscriptionStatus::Active)
            ->orderBy('boutique.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return array{items: list<Boutique>, total: int} */
    public function findPublishedForPublicPaginated(int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('boutique')
            ->innerJoin('boutique.subscriptions', 'subscription')
            ->distinct()
            ->andWhere('boutique.deletedAt IS NULL')
            ->andWhere('boutique.status = :status')
            ->andWhere('boutique.isPublished = :published')
            ->andWhere('subscription.status = :subStatus')
            ->setParameter('status', \App\Enum\BoutiqueStatus::Active)
            ->setParameter('published', true)
            ->setParameter('subStatus', \App\Enum\SubscriptionStatus::Active);

        $countQuery = clone $query;
        $total = (int) $countQuery
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT boutique.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $query
            ->orderBy('boutique.createdAt', 'DESC')
            ->addOrderBy('boutique.id', 'DESC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /** @return array{items: list<Boutique>, total: int} */
    private function paginateBoutiques(QueryBuilder $query, int $page, int $itemsPerPage): array
    {
        $countQuery = clone $query;
        $total = (int) $countQuery
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(boutique.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $query
            ->orderBy('boutique.createdAt', 'DESC')
            ->addOrderBy('boutique.id', 'DESC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /** @return list<Boutique> */
    public function findPendingValidation(): array
    {
        return $this->findBy(['status' => \App\Enum\BoutiqueStatus::Pending, 'deletedAt' => null], ['createdAt' => 'DESC']);
    }

    public function countPublishedByOwner(\App\Entity\User $owner): int
    {
        return (int) $this->createQueryBuilder('boutique')
            ->select('COUNT(boutique.id)')
            ->andWhere('boutique.owner = :owner')
            ->andWhere('boutique.isPublished = :published')
            ->andWhere('boutique.deletedAt IS NULL')
            ->setParameter('owner', $owner)
            ->setParameter('published', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countOwnedByOwner(\App\Entity\User $owner): int
    {
        return (int) $this->createQueryBuilder('boutique')
            ->select('COUNT(boutique.id)')
            ->andWhere('boutique.owner = :owner')
            ->andWhere('boutique.deletedAt IS NULL')
            ->andWhere('boutique.status != :archived')
            ->setParameter('owner', $owner)
            ->setParameter('archived', \App\Enum\BoutiqueStatus::Archived)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
