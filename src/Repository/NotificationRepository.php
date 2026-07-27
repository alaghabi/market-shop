<?php

namespace App\Repository;

use App\Entity\Boutique;
use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Notification> */
final class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /** @return array{items: list<Notification>, total: int} */
    public function findForRecipient(?string $recipientIdentifier, bool $isSuperAdmin, ?Boutique $boutique, int $page, int $itemsPerPage): array
    {
        $queryBuilder = $this->createQueryBuilder('notification')
            ->orderBy('notification.createdAt', 'DESC');

        if (!$isSuperAdmin) {
            $queryBuilder
                ->andWhere('notification.recipientIdentifier = :recipient')
                ->setParameter('recipient', $recipientIdentifier);
        }

        if ($boutique instanceof Boutique) {
            $queryBuilder
                ->andWhere('notification.boutique = :boutique')
                ->setParameter('boutique', $boutique);
        }

        $countQuery = clone $queryBuilder;
        $total = (int) $countQuery
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(notification.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $queryBuilder
            ->addOrderBy('notification.id', 'DESC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
