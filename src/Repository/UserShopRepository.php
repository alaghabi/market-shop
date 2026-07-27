<?php

namespace App\Repository;

use App\Entity\UserShop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<UserShop> */
final class UserShopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserShop::class);
    }

    /** @return UserShop[] */
    public function findByUser(string $userId): array
    {
        return $this->createQueryBuilder('us')
            ->andWhere('us.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }

    /** @return UserShop[] */
    public function findByBoutique(string $boutiqueId): array
    {
        return $this->createQueryBuilder('us')
            ->andWhere('us.boutique = :boutiqueId')
            ->setParameter('boutiqueId', $boutiqueId)
            ->getQuery()
            ->getResult();
    }

    /** @return UserShop[] */
    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('us')
            ->innerJoin('us.user', 'u')
            ->innerJoin('us.boutique', 'b')
            ->andWhere('us.role = :role')
            ->setParameter('role', $role)
            ->orderBy('b.name', 'ASC')
            ->addOrderBy('u.identifier', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return UserShop[] */
    public function findByRoleAndBoutique(string $role, string $boutiqueId): array
    {
        return $this->createQueryBuilder('us')
            ->innerJoin('us.user', 'u')
            ->innerJoin('us.boutique', 'b')
            ->andWhere('us.role = :role')
            ->andWhere('us.boutique = :boutiqueId')
            ->setParameter('role', $role)
            ->setParameter('boutiqueId', $boutiqueId)
            ->orderBy('u.identifier', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array{items: list<UserShop>, total: int} */
    public function findForBackoffice(
        ?string $role,
        ?string $boutiqueId,
        array $boutiqueIds,
        int $page,
        int $itemsPerPage,
    ): array {
        $query = $this->createQueryBuilder('userShop')
            ->innerJoin('userShop.user', 'user')
            ->innerJoin('userShop.boutique', 'boutique');

        if (null !== $role) {
            $query->andWhere('userShop.role = :role')
                ->setParameter('role', $role);
        }

        if (null !== $boutiqueId) {
            $query->andWhere('userShop.boutique = :boutiqueId')
                ->setParameter('boutiqueId', $boutiqueId);
        } elseif ([] !== $boutiqueIds) {
            $query->andWhere('userShop.boutique IN (:boutiqueIds)')
                ->setParameter('boutiqueIds', $boutiqueIds);
        }

        $countQuery = clone $query;
        $total = (int) $countQuery
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(userShop.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $query
            ->orderBy('boutique.name', 'ASC')
            ->addOrderBy('user.identifier', 'ASC')
            ->addOrderBy('userShop.id', 'ASC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function findOneByUserAndBoutique(string $userId, string $boutiqueId): ?UserShop
    {
        return $this->createQueryBuilder('us')
            ->andWhere('us.user = :userId')
            ->andWhere('us.boutique = :boutiqueId')
            ->setParameter('userId', $userId)
            ->setParameter('boutiqueId', $boutiqueId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
