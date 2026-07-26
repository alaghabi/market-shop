<?php

namespace App\Repository;

use App\Entity\DeliveryCompany;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DeliveryCompany> */
final class DeliveryCompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeliveryCompany::class);
    }

    /** @return list<DeliveryCompany> */
    public function findActive(): array
    {
        return $this->createQueryBuilder('dc')
            ->andWhere('dc.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('dc.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneBySlug(string $slug): ?DeliveryCompany
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
