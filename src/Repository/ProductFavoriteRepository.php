<?php

namespace App\Repository;

use App\Entity\ProductFavorite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductFavorite>
 */
class ProductFavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductFavorite::class);
    }

    public function findByProductAndUser(int $productId, string $userId): ?ProductFavorite
    {
        return $this->findOneBy([
            'productId' => $productId,
            'userId' => $userId,
        ]);
    }

    public function isFavorite(int $productId, string $userId): bool
    {
        return $this->findByProductAndUser($productId, $userId) !== null;
    }

    public function getUserFavorites(string $userId): array
    {
        return $this->findBy(['userId' => $userId], ['createdAt' => 'DESC']);
    }

    public function getProductFavoriteCount(int $productId): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.productId = :productId')
            ->setParameter('productId', $productId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
