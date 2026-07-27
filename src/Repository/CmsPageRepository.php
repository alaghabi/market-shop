<?php

namespace App\Repository;

use App\Entity\Boutique;
use App\Entity\CmsPage;
use App\Enum\CmsPageStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CmsPage> */
final class CmsPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CmsPage::class);
    }

    /** @return CmsPage[] */
    public function findByBoutique(Boutique $boutique): array
    {
        return $this->findBy(['boutique' => $boutique], ['sortOrder' => 'ASC', 'createdAt' => 'DESC']);
    }

    /** @return array{items: list<CmsPage>, total: int} */
    public function findForBackoffice(?Boutique $boutique, int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('page');

        if ($boutique instanceof Boutique) {
            $query->andWhere('page.boutique = :boutique')
                ->setParameter('boutique', $boutique);
        }

        $countQuery = clone $query;
        $total = (int) $countQuery
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(page.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $query
            ->orderBy('page.sortOrder', 'ASC')
            ->addOrderBy('page.createdAt', 'DESC')
            ->addOrderBy('page.id', 'ASC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /** @return CmsPage[] */
    public function findPublishedByBoutique(Boutique $boutique): array
    {
        return $this->findBy(
            ['boutique' => $boutique, 'status' => CmsPageStatus::Published],
            ['sortOrder' => 'ASC'],
        );
    }

    /** @return CmsPage[] */
    public function findPublishedByBoutiqueAndHeader(Boutique $boutique): array
    {
        return $this->findBy(
            ['boutique' => $boutique, 'status' => CmsPageStatus::Published, 'showInHeader' => true],
            ['sortOrder' => 'ASC', 'createdAt' => 'DESC'],
        );
    }

    public function findOneByBoutiqueAndSlug(Boutique $boutique, string $slug): ?CmsPage
    {
        return $this->findOneBy(['boutique' => $boutique, 'slug' => $slug]);
    }

    public function findHomepage(Boutique $boutique): ?CmsPage
    {
        return $this->findOneBy(['boutique' => $boutique, 'isHomepage' => true]);
    }

    public function slugExistsInBoutique(Boutique $boutique, string $slug, ?string $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.boutique = :boutique')
            ->andWhere('p.slug = :slug')
            ->setParameter('boutique', $boutique)
            ->setParameter('slug', $slug);

        if (null !== $excludeId) {
            $qb->andWhere('p.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function countPublishedByBoutique(Boutique $boutique): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.boutique = :boutique')
            ->andWhere('p.status = :status')
            ->setParameter('boutique', $boutique)
            ->setParameter('status', CmsPageStatus::Published)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
