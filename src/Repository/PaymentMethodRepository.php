<?php

namespace App\Repository;

use App\Entity\PaymentMethod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PaymentMethod> */
final class PaymentMethodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentMethod::class);
    }

    /** @return list<PaymentMethod> */
    public function findActive(): array
    {
        return $this->findBy(['isActive' => true], ['name' => 'ASC']);
    }

    /** @return list<PaymentMethod> */
    public function findActiveVisible(): array
    {
        return $this->findBy(['isActive' => true, 'isVisible' => true], ['name' => 'ASC']);
    }

    /** @return array{items: list<PaymentMethod>, total: int} */
    public function findForBackoffice(int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('paymentMethod');
        $countQuery = clone $query;
        $total = (int) $countQuery->resetDQLPart('select')->resetDQLPart('orderBy')
            ->select('COUNT(paymentMethod.id)')->getQuery()->getSingleScalarResult();
        $items = $query->orderBy('paymentMethod.name', 'ASC')->addOrderBy('paymentMethod.id', 'ASC')
            ->setFirstResult(($page - 1) * $itemsPerPage)->setMaxResults($itemsPerPage)
            ->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function findOneByCode(string $code): ?PaymentMethod
    {
        return $this->findOneBy(['code' => $code]);
    }
}
