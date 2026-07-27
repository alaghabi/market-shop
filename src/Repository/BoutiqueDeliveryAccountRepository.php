<?php

namespace App\Repository;

use App\Entity\Boutique;
use App\Entity\BoutiqueDeliveryAccount;
use App\Entity\DeliveryCompany;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BoutiqueDeliveryAccount> */
final class BoutiqueDeliveryAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BoutiqueDeliveryAccount::class);
    }

    /** @return list<BoutiqueDeliveryAccount> */
    public function findByBoutique(Boutique $boutique): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.boutique = :boutique')
            ->setParameter('boutique', $boutique)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return array{items: list<BoutiqueDeliveryAccount>, total: int} */
    public function findForBackoffice(Boutique $boutique, int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('account')->andWhere('account.boutique = :boutique')
            ->setParameter('boutique', $boutique);
        $countQuery = clone $query;
        $total = (int) $countQuery->resetDQLPart('select')->resetDQLPart('orderBy')
            ->select('COUNT(account.id)')->getQuery()->getSingleScalarResult();
        $items = $query->orderBy('account.createdAt', 'DESC')->addOrderBy('account.id', 'DESC')
            ->setFirstResult(($page - 1) * $itemsPerPage)->setMaxResults($itemsPerPage)
            ->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /** @return list<BoutiqueDeliveryAccount> */
    public function findActiveByBoutique(Boutique $boutique): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.boutique = :boutique')
            ->andWhere('a.isActive = :active')
            ->andWhere('a.isVerified = :verified')
            ->setParameter('boutique', $boutique)
            ->setParameter('active', true)
            ->setParameter('verified', true)
            ->getQuery()
            ->getResult();
    }

    public function findOneByBoutiqueAndCompany(Boutique $boutique, DeliveryCompany $company): ?BoutiqueDeliveryAccount
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.boutique = :boutique')
            ->andWhere('a.deliveryCompany = :company')
            ->setParameter('boutique', $boutique)
            ->setParameter('company', $company)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findDefaultForBoutique(Boutique $boutique): ?BoutiqueDeliveryAccount
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.boutique = :boutique')
            ->andWhere('a.isDefault = :default')
            ->andWhere('a.isActive = :active')
            ->andWhere('a.isVerified = :verified')
            ->setParameter('boutique', $boutique)
            ->setParameter('default', true)
            ->setParameter('active', true)
            ->setParameter('verified', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function clearDefaultForBoutique(Boutique $boutique): void
    {
        $this->createQueryBuilder('a')
            ->update()
            ->set('a.isDefault', ':false')
            ->andWhere('a.boutique = :boutique')
            ->setParameter('false', false)
            ->setParameter('boutique', $boutique)
            ->getQuery()
            ->execute();
    }
}
