<?php

namespace App\Repository;

use App\Entity\Boutique;
use App\Entity\ShopModule;
use App\Entity\SubscriptionPlanModule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ShopModule> */
final class ShopModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopModule::class);
    }

    public function findOneByBoutiqueAndModule(Boutique $boutique, SubscriptionPlanModule $module): ?ShopModule
    {
        return $this->findOneBy(['boutique' => $boutique, 'module' => $module]);
    }

    /** @return ShopModule[] */
    public function findByBoutique(Boutique $boutique): array
    {
        return $this->findBy(['boutique' => $boutique], ['createdAt' => 'ASC']);
    }

    /** @return array{items: list<ShopModule>, total: int} */
    public function findForBackoffice(Boutique $boutique, int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('shopModule')
            ->andWhere('shopModule.boutique = :boutique')
            ->setParameter('boutique', $boutique);

        $countQuery = clone $query;
        $total = (int) $countQuery->resetDQLPart('select')->resetDQLPart('orderBy')
            ->select('COUNT(shopModule.id)')->getQuery()->getSingleScalarResult();

        $items = $query->orderBy('shopModule.createdAt', 'ASC')->addOrderBy('shopModule.id', 'ASC')
            ->setFirstResult(($page - 1) * $itemsPerPage)->setMaxResults($itemsPerPage)
            ->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /** @return ShopModule[] */
    public function findEnabledByBoutique(Boutique $boutique): array
    {
        return $this->findBy(['boutique' => $boutique, 'isEnabled' => true]);
    }
}
