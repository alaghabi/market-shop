<?php

namespace App\Repository;

use App\Entity\Boutique;
use App\Entity\PaymentMethod;
use App\Entity\ShopPaymentMethod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ShopPaymentMethod> */
final class ShopPaymentMethodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopPaymentMethod::class);
    }

    /** @return list<ShopPaymentMethod> */
    public function findByBoutique(Boutique $boutique): array
    {
        return $this->createQueryBuilder('spm')
            ->innerJoin('spm.paymentMethod', 'pm')
            ->andWhere('spm.boutique = :boutique')
            ->setParameter('boutique', $boutique)
            ->orderBy('spm.displayOrder', 'ASC')
            ->addOrderBy('pm.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array{items: list<ShopPaymentMethod>, total: int} */
    public function findForBackoffice(Boutique $boutique, bool $activeOnly, int $page, int $itemsPerPage): array
    {
        $query = $this->createQueryBuilder('shopMethod')
            ->innerJoin('shopMethod.paymentMethod', 'paymentMethod')
            ->andWhere('shopMethod.boutique = :boutique')
            ->setParameter('boutique', $boutique);

        if ($activeOnly) {
            $query
                ->andWhere('shopMethod.isActive = true')
                ->andWhere('paymentMethod.isActive = true')
                ->andWhere('paymentMethod.isVisible = true');
        }

        $countQuery = clone $query;
        $total = (int) $countQuery
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(shopMethod.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $query
            ->orderBy('shopMethod.displayOrder', 'ASC')
            ->addOrderBy('paymentMethod.name', 'ASC')
            ->addOrderBy('shopMethod.id', 'ASC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /** @return list<ShopPaymentMethod> */
    public function findActiveForBoutique(Boutique $boutique): array
    {
        return $this->createQueryBuilder('spm')
            ->innerJoin('spm.paymentMethod', 'pm')
            ->andWhere('spm.boutique = :boutique')
            ->andWhere('spm.isActive = true')
            ->andWhere('pm.isActive = true')
            ->andWhere('pm.isVisible = true')
            ->setParameter('boutique', $boutique)
            ->orderBy('spm.displayOrder', 'ASC')
            ->addOrderBy('pm.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<ShopPaymentMethod> */
    public function findStorefrontForBoutique(Boutique $boutique): array
    {
        return $this->findActiveForBoutique($boutique);
    }

    public function findOneByBoutiqueAndMethod(Boutique $boutique, PaymentMethod $paymentMethod): ?ShopPaymentMethod
    {
        return $this->findOneBy(['boutique' => $boutique, 'paymentMethod' => $paymentMethod]);
    }

    public function hasActiveCodeForBoutique(Boutique $boutique, string $code): bool
    {
        return 0 < (int) $this->createQueryBuilder('spm')
            ->select('COUNT(spm.id)')
            ->innerJoin('spm.paymentMethod', 'pm')
            ->andWhere('spm.boutique = :boutique')
            ->andWhere('spm.isActive = true')
            ->andWhere('pm.isActive = true')
            ->andWhere('pm.isVisible = true')
            ->andWhere('pm.code = :code')
            ->setParameter('boutique', $boutique)
            ->setParameter('code', $code)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
