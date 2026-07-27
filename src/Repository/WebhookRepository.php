<?php

namespace App\Repository;

use App\Entity\Webhook;
use App\Enum\WebhookEventType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Webhook> */
final class WebhookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Webhook::class);
    }

    /** @return list<Webhook> */
    public function findListeningTo(WebhookEventType $event, ?string $boutiqueId = null): array
    {
        $qb = $this->createQueryBuilder('w')
            ->andWhere('w.status = :status')
            ->andWhere('JSON_CONTAINS(w.events, :eventJson) = 1')
            ->setParameter('status', 'ACTIVE')
            ->setParameter('eventJson', json_encode($event->value));

        if (null !== $boutiqueId) {
            $qb->andWhere('w.boutique = :boutiqueId OR w.boutique IS NULL')
                ->setParameter('boutiqueId', $boutiqueId);
        } else {
            $qb->andWhere('w.boutique IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<Webhook> */
    public function findAllAdmin(int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('w')
            ->orderBy('w.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /** @return array{items: list<Webhook>, total: int} */
    public function findForBackoffice(int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('webhook');
        $countQuery = clone $query;
        $total = (int) $countQuery->resetDQLPart('select')->resetDQLPart('orderBy')
            ->select('COUNT(webhook.id)')->getQuery()->getSingleScalarResult();
        $items = $query->orderBy('webhook.createdAt', 'DESC')->addOrderBy('webhook.id', 'DESC')
            ->setFirstResult(($page - 1) * $itemsPerPage)->setMaxResults($itemsPerPage)
            ->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
