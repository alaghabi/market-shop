<?php

namespace App\Repository;

use App\Entity\Boutique;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Category> */
final class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /** @return Category[] */
    public function findByBoutique(Boutique $boutique): array
    {
        return $this->findBy(['boutique' => $boutique, 'deletedAt' => null], ['name' => 'ASC']);
    }

    /** @return array{items: list<Category>, total: int} */
    public function findForBackoffice(?Boutique $boutique, int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('category')
            ->andWhere('category.deletedAt IS NULL');

        if ($boutique instanceof Boutique) {
            $query->andWhere('category.boutique = :boutique')
                ->setParameter('boutique', $boutique);
        }

        $countQuery = clone $query;
        $total = (int) $countQuery
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(category.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $query
            ->orderBy('category.name', 'ASC')
            ->addOrderBy('category.id', 'ASC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /** @return Category[] */
    public function findActiveByBoutique(Boutique $boutique): array
    {
        return $this->findBy(
            ['boutique' => $boutique, 'isActive' => true, 'deletedAt' => null],
            ['name' => 'ASC'],
        );
    }

    public function findBySlugOrId(string $identifier, ?Boutique $boutique = null): ?Category
    {
        if (Uuid::isValid($identifier)) {
            $category = $this->find($identifier);
            if (null === $boutique || (null !== $category && (string) $category->getBoutique()->getId() === (string) $boutique->getId())) {
                return $category;
            }

            return null;
        }

        if (null === $boutique) {
            return null;
        }

        return $this->findOneBy(['boutique' => $boutique, 'slug' => $identifier, 'deletedAt' => null]);
    }

    /** @return Category[] */
    public function findRootByBoutique(Boutique $boutique): array
    {
        return $this->findBy(
            ['boutique' => $boutique, 'parent' => null, 'deletedAt' => null],
            ['name' => 'ASC'],
        );
    }

    /** @return Category[] */
    public function findByBoutiqueAndParent(Boutique $boutique, ?Category $parent = null): array
    {
        return $this->findBy(
            ['boutique' => $boutique, 'parent' => $parent, 'deletedAt' => null],
            ['name' => 'ASC'],
        );
    }

    public function slugExistsInBoutique(string $slug, Boutique $boutique, ?string $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.boutique = :boutique')
            ->andWhere('c.slug = :slug')
            ->setParameter('boutique', $boutique)
            ->setParameter('slug', $slug);

        if (null !== $excludeId) {
            $qb->andWhere('c.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /** @return list<Category> */
    public function findSeoIndexedByBoutique(Boutique $boutique): array
    {
        return $this->findBy(
            ['boutique' => $boutique, 'isActive' => true, 'showCategoryPage' => true, 'deletedAt' => null],
            ['name' => 'ASC'],
        );
    }

    public function countSeoIndexedByBoutique(Boutique $boutique): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.boutique = :boutique')
            ->andWhere('c.isActive = true')
            ->andWhere('c.showCategoryPage = true')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('boutique', $boutique)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
