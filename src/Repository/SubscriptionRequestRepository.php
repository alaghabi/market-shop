<?php

namespace App\Repository;

use App\Entity\Boutique;
use App\Entity\SubscriptionRequest;
use App\Enum\Subscription\SubscriptionRequestStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SubscriptionRequest> */
final class SubscriptionRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubscriptionRequest::class);
    }

    /** @return list<SubscriptionRequest> */
    public function findPendingByBoutique(Boutique $boutique): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.boutique = :boutique')
            ->andWhere('r.status = :status')
            ->setParameter('boutique', $boutique)
            ->setParameter('status', SubscriptionRequestStatus::Pending)
            ->orderBy('r.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return array{items: list<SubscriptionRequest>, total: int} */
    public function findForBackoffice(?Boutique $boutique, int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('request');

        if ($boutique instanceof Boutique) {
            $query->andWhere('request.boutique = :boutique')
                ->setParameter('boutique', $boutique);
        }

        $countQuery = clone $query;
        $total = (int) $countQuery
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(request.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $query
            ->orderBy('request.requestedAt', 'DESC')
            ->addOrderBy('request.id', 'DESC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
