<?php

namespace App\Repository;

use App\Entity\RolePermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RolePermission> */
final class RolePermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RolePermission::class);
    }

    /** @return RolePermission[] */
    public function findByRole(string $roleCode): array
    {
        return $this->createQueryBuilder('rp')
            ->andWhere('rp.roleCode = :roleCode')
            ->setParameter('roleCode', $roleCode)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<string> $roles
     *
     * @return list<string>
     */
    public function findPermissionsByRoles(array $roles): array
    {
        if ([] === $roles) {
            return [];
        }

        $permissions = $this->createQueryBuilder('rp')
            ->select('rp.permission')
            ->andWhere('rp.roleCode IN (:roles)')
            ->setParameter('roles', $roles)
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_unique(array_map('strval', $permissions)));
    }
}
