<?php

namespace App\Repository;

use App\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Permission> */
final class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    /** @return Permission[] */
    public function findByModule(string $module): array
    {
        return $this->findBy(['module' => $module], ['code' => 'ASC']);
    }

    /** @return array{items: list<Permission>, total: int} */
    public function findForBackoffice(?string $module, int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('permission');
        if (null !== $module && '' !== $module) {
            $query->andWhere('permission.module = :module')->setParameter('module', $module);
        }
        $countQuery = clone $query;
        $total = (int) $countQuery->resetDQLPart('select')->resetDQLPart('orderBy')
            ->select('COUNT(permission.id)')->getQuery()->getSingleScalarResult();
        $items = $query->orderBy('permission.module', 'ASC')->addOrderBy('permission.code', 'ASC')
            ->addOrderBy('permission.id', 'ASC')->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
