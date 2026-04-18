<?php

namespace App\Repository;

use App\Entity\ProductRating;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductRating>
 */
class ProductRatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductRating::class);
    }

    public function findByProductAndUser(int $productId, string $userId): ?ProductRating
    {
        return $this->findOneBy([
            'productId' => $productId,
            'userId' => $userId,
        ]);
    }

    public function getAverageRating(int $productId): ?float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) as avgRating, COUNT(r.id) as totalRatings')
            ->where('r.productId = :productId')
            ->setParameter('productId', $productId)
            ->getQuery()
            ->getOneOrNullResult();

        if ($result && $result['avgRating'] !== null) {
            return round((float) $result['avgRating'], 1);
        }

        return null;
    }

    public function getTotalRatings(int $productId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.productId = :productId')
            ->setParameter('productId', $productId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
