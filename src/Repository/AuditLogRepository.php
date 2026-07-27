<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AuditLog> */
final class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /** @return list<AuditLog> */
    public function findByBoutique(?string $boutiqueId, int $limit = 100, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('a');

        if (null !== $boutiqueId) {
            $qb->andWhere('a.boutique = :boutiqueId')
                ->setParameter('boutiqueId', $boutiqueId);
        }

        return $qb->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /** @return array{items: list<AuditLog>, total: int} */
    public function findForBackoffice(?string $boutiqueId, int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('auditLog');
        if (null !== $boutiqueId) {
            $query->andWhere('auditLog.boutique = :boutiqueId')->setParameter('boutiqueId', $boutiqueId);
        }
        $countQuery = clone $query;
        $total = (int) $countQuery->resetDQLPart('select')->resetDQLPart('orderBy')
            ->select('COUNT(auditLog.id)')->getQuery()->getSingleScalarResult();
        $items = $query->orderBy('auditLog.createdAt', 'DESC')->addOrderBy('auditLog.id', 'DESC')
            ->setFirstResult(($page - 1) * $itemsPerPage)->setMaxResults($itemsPerPage)
            ->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
