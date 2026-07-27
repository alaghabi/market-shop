<?php

namespace App\Repository;

use App\Entity\Boutique;
use App\Entity\Product;
use App\Entity\Review;
use App\Enum\ReviewStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Review> */
final class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /** @return list<Review> */
    public function findApprovedByBoutique(Boutique $boutique): array
    {
        return $this->createQueryBuilder('review')
            ->andWhere('review.boutique = :boutique')
            ->andWhere('review.status = :status')
            ->setParameter('boutique', $boutique)
            ->setParameter('status', ReviewStatus::Approved)
            ->orderBy('review.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Review> */
    public function findApprovedByProduct(Product $product): array
    {
        return $this->createQueryBuilder('review')
            ->andWhere('review.product = :product')
            ->andWhere('review.status = :status')
            ->setParameter('product', $product)
            ->setParameter('status', ReviewStatus::Approved)
            ->orderBy('review.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Review> */
    public function findByBoutiqueForAdmin(Boutique $boutique): array
    {
        return $this->createQueryBuilder('review')
            ->andWhere('review.boutique = :boutique')
            ->setParameter('boutique', $boutique)
            ->orderBy('review.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Review> */
    public function findPending(): array
    {
        return $this->createQueryBuilder('review')
            ->andWhere('review.status = :status')
            ->setParameter('status', ReviewStatus::Pending)
            ->orderBy('review.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getAverageRatingByBoutique(Boutique $boutique): ?float
    {
        $result = $this->createQueryBuilder('review')
            ->select('AVG(review.rating) as avg')
            ->andWhere('review.boutique = :boutique')
            ->andWhere('review.status = :status')
            ->setParameter('boutique', $boutique)
            ->setParameter('status', ReviewStatus::Approved)
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $result ? (float) $result : null;
    }

    public function getAverageRatingByProduct(Product $product): ?float
    {
        $result = $this->createQueryBuilder('review')
            ->select('AVG(review.rating) as avg')
            ->andWhere('review.product = :product')
            ->andWhere('review.status = :status')
            ->setParameter('product', $product)
            ->setParameter('status', ReviewStatus::Approved)
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $result ? (float) $result : null;
    }

    public function countByBoutique(Boutique $boutique): int
    {
        return $this->createQueryBuilder('review')
            ->select('COUNT(review.id)')
            ->andWhere('review.boutique = :boutique')
            ->andWhere('review.status = :status')
            ->setParameter('boutique', $boutique)
            ->setParameter('status', ReviewStatus::Approved)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByProduct(Product $product): int
    {
        return $this->createQueryBuilder('review')
            ->select('COUNT(review.id)')
            ->andWhere('review.product = :product')
            ->andWhere('review.status = :status')
            ->setParameter('product', $product)
            ->setParameter('status', ReviewStatus::Approved)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countRecentByIpHash(string $ipHash, int $hours = 1): int
    {
        return (int) $this->createQueryBuilder('review')
            ->select('COUNT(review.id)')
            ->andWhere('review.ipHash = :ipHash')
            ->andWhere('review.createdAt >= :since')
            ->setParameter('ipHash', $ipHash)
            ->setParameter('since', new \DateTimeImmutable(sprintf('-%d hour', $hours)))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function existsForIpAndProduct(string $ipHash, Product $product): bool
    {
        return 0 < (int) $this->createQueryBuilder('review')
            ->select('COUNT(review.id)')
            ->andWhere('review.ipHash = :ipHash')
            ->andWhere('review.product = :product')
            ->setParameter('ipHash', $ipHash)
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function existsForUserAndProduct(\App\Entity\User $user, Product $product): bool
    {
        return 0 < (int) $this->createQueryBuilder('review')
            ->select('COUNT(review.id)')
            ->andWhere('review.user = :user')
            ->andWhere('review.product = :product')
            ->setParameter('user', $user)
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function existsForUserAndBoutique(\App\Entity\User $user, Boutique $boutique): bool
    {
        return 0 < (int) $this->createQueryBuilder('review')
            ->select('COUNT(review.id)')
            ->andWhere('review.user = :user')
            ->andWhere('review.product IS NULL')
            ->andWhere('review.boutique = :boutique')
            ->setParameter('user', $user)
            ->setParameter('boutique', $boutique)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function existsForBrowserAndProduct(string $browserHash, Product $product): bool
    {
        return 0 < (int) $this->createQueryBuilder('review')
            ->select('COUNT(review.id)')
            ->andWhere('review.browserHash = :browserHash')
            ->andWhere('review.product = :product')
            ->setParameter('browserHash', $browserHash)
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function existsForBrowserAndBoutique(string $browserHash, Boutique $boutique): bool
    {
        return 0 < (int) $this->createQueryBuilder('review')
            ->select('COUNT(review.id)')
            ->andWhere('review.browserHash = :browserHash')
            ->andWhere('review.product IS NULL')
            ->andWhere('review.boutique = :boutique')
            ->setParameter('browserHash', $browserHash)
            ->setParameter('boutique', $boutique)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function existsForIpAndBoutique(string $ipHash, Boutique $boutique): bool
    {
        return 0 < (int) $this->createQueryBuilder('review')
            ->select('COUNT(review.id)')
            ->andWhere('review.ipHash = :ipHash')
            ->andWhere('review.product IS NULL')
            ->andWhere('review.boutique = :boutique')
            ->setParameter('ipHash', $ipHash)
            ->setParameter('boutique', $boutique)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function existsRecentGuestEmailForProduct(string $email, Product $product): bool
    {
        return 0 < (int) $this->createQueryBuilder('review')
            ->select('COUNT(review.id)')
            ->andWhere('LOWER(review.authorEmail) = :email')
            ->andWhere('review.product = :product')
            ->andWhere('review.createdAt >= :since')
            ->setParameter('email', mb_strtolower($email))
            ->setParameter('product', $product)
            ->setParameter('since', new \DateTimeImmutable('-30 days'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function existsRecentGuestEmailForBoutique(string $email, Boutique $boutique): bool
    {
        return 0 < (int) $this->createQueryBuilder('review')
            ->select('COUNT(review.id)')
            ->andWhere('LOWER(review.authorEmail) = :email')
            ->andWhere('review.product IS NULL')
            ->andWhere('review.boutique = :boutique')
            ->andWhere('review.createdAt >= :since')
            ->setParameter('email', mb_strtolower($email))
            ->setParameter('boutique', $boutique)
            ->setParameter('since', new \DateTimeImmutable('-30 days'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Review> */
    public function findApprovedPlatformReviews(): array
    {
        return $this->createQueryBuilder('review')
            ->andWhere('review.product IS NULL')
            ->andWhere('review.boutique IS NULL')
            ->andWhere('review.status = :status')
            ->setParameter('status', ReviewStatus::Approved)
            ->orderBy('review.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Review> */
    public function findPlatformReviewsForAdmin(): array
    {
        return $this->createQueryBuilder('review')
            ->andWhere('review.product IS NULL')
            ->andWhere('review.boutique IS NULL')
            ->orderBy('review.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
