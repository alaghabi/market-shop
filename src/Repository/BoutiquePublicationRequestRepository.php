<?php

namespace App\Repository;

use App\Entity\Boutique;
use App\Entity\BoutiquePublicationRequest;
use App\Enum\Subscription\SubscriptionRequestStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BoutiquePublicationRequest> */
final class BoutiquePublicationRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BoutiquePublicationRequest::class);
    }

    public function findPendingByBoutique(Boutique $boutique): ?BoutiquePublicationRequest
    {
        return $this->findOneBy([
            'boutique' => $boutique,
            'status' => SubscriptionRequestStatus::Pending,
        ], ['requestedAt' => 'DESC']);
    }

    /** @return array{items: list<BoutiquePublicationRequest>, total: int} */
    public function findForBackoffice(?Boutique $boutique, int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('request');
        if ($boutique instanceof Boutique) {
            $query->andWhere('request.boutique = :boutique')->setParameter('boutique', $boutique);
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
