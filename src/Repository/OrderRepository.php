<?php

namespace App\Repository;

use App\Entity\Boutique;
use App\Entity\User;
use App\Entity\Order;
use App\Enum\ProductStatus;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Order> */
final class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function hasOrdersByUserForBoutique(User $user, Boutique $boutique): bool
    {
        return 0 < (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->innerJoin('o.customer', 'customer')
            ->andWhere('customer.user = :user')
            ->andWhere('o.boutique = :boutique')
            ->setParameter('user', $user)
            ->setParameter('boutique', $boutique)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count of non-cancelled orders placed by this customer for this boutique —
     * used by the loyalty engine for first-purchase/order-count/min-orders rules.
     */
    public function countValidByCustomer(\App\Entity\Customer $customer): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.customer = :customer')
            ->andWhere('o.status != :cancelled')
            ->setParameter('customer', $customer)
            ->setParameter('cancelled', OrderStatus::Cancelled)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Order> */
    public function findPaid(): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->setParameter('status', OrderStatus::Paid)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Order> */
    public function findShippedNotDelivered(): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->andWhere('o.deliveredAt IS NULL')
            ->setParameter('status', OrderStatus::Shipped)
            ->getQuery()
            ->getResult();
    }

    public function findForPublicTracking(Boutique $boutique, string $reference): ?Order
    {
        $reference = ltrim(trim($reference), '#');
        if (!Uuid::isValid($reference)) {
            return null;
        }

        return $this->createQueryBuilder('o')
            ->andWhere('o.id = :id')
            ->andWhere('o.boutique = :boutique')
            ->setParameter('id', Uuid::fromString($reference))
            ->setParameter('boutique', $boutique)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return array{productId: string, quantitySold: int}|null */
    public function findMostOrderedProductSince(Boutique $boutique, \DateTimeImmutable $since): ?array
    {
        $result = $this->createQueryBuilder('o')
            ->select('product.id AS productId, SUM(item.quantity) AS quantitySold')
            ->innerJoin('o.items', 'item')
            ->innerJoin('item.product', 'product')
            ->andWhere('o.boutique = :boutique')
            ->andWhere('o.createdAt >= :since')
            ->andWhere('o.status IN (:statuses)')
            ->andWhere('product.deletedAt IS NULL')
            ->andWhere('product.status = :productStatus')
            ->setParameter('boutique', $boutique)
            ->setParameter('since', $since)
            ->setParameter('statuses', [
                OrderStatus::Paid,
                OrderStatus::Completed,
                OrderStatus::Shipped,
                OrderStatus::Delivered,
            ])
            ->setParameter('productStatus', ProductStatus::Active)
            ->groupBy('product.id')
            ->orderBy('quantitySold', 'DESC')
            ->addOrderBy('product.name', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!is_array($result) || !isset($result['productId'])) {
            return null;
        }

        return [
            'productId' => (string) $result['productId'],
            'quantitySold' => (int) $result['quantitySold'],
        ];
    }

    /** @return list<Order> */
    public function findDeliveryFailedForRetry(int $maxRetries = 5, int $retryIntervalSeconds = 3600): array
    {
        $threshold = new \DateTimeImmutable(sprintf('-%d seconds', $retryIntervalSeconds));

        return $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->andWhere('o.submittedToDelivery = :submitted')
            ->andWhere('o.deliveryRetryCount < :maxRetries')
            ->andWhere('o.lastRetryAt IS NULL OR o.lastRetryAt < :threshold')
            ->setParameter('status', OrderStatus::Paid)
            ->setParameter('submitted', false)
            ->setParameter('maxRetries', $maxRetries)
            ->setParameter('threshold', $threshold)
            ->orderBy('o.lastRetryAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array{items: list<Order>, total: int} */
    public function findForBackoffice(
        ?Boutique $boutique = null,
        ?OrderStatus $status = null,
        ?string $search = null,
        string $sortField = 'createdAt',
        string $sortDirection = 'DESC',
        int $page = 1,
        int $itemsPerPage = 20,
    ): array {
        $query = $this->createQueryBuilder('o');

        if ($boutique instanceof Boutique) {
            $query->andWhere('o.boutique = :boutique')->setParameter('boutique', $boutique);
        }

        if ($status instanceof OrderStatus) {
            $query->andWhere('o.status = :status')->setParameter('status', $status);
        }

        if (null !== $search && '' !== trim($search)) {
            $query
                ->andWhere('LOWER(COALESCE(o.customerName, :empty)) LIKE :search OR LOWER(COALESCE(o.customerEmail, :empty)) LIKE :search')
                ->setParameter('empty', '')
                ->setParameter('search', '%'.mb_strtolower(trim($search)).'%');
        }

        $allowedSortFields = ['createdAt', 'totalCents', 'status', 'customerName'];
        $sortField = in_array($sortField, $allowedSortFields, true) ? $sortField : 'createdAt';
        $sortDirection = 'ASC' === strtoupper($sortDirection) ? 'ASC' : 'DESC';

        $countQuery = clone $query;
        $total = (int) $countQuery
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $query
            ->orderBy('o.'.$sortField, $sortDirection)
            ->addOrderBy('o.id', $sortDirection)
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
