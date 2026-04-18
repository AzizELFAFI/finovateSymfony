<?php

namespace App\Repository;

use App\Entity\AdRating;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdRating>
 */
class AdRatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdRating::class);
    }

    public function findByAdAndUser(int $adId, string $userId): ?AdRating
    {
        return $this->findOneBy([
            'adId' => $adId,
            'userId' => $userId,
        ]);
    }

    public function getAverageRating(int $adId): ?float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) as avgRating, COUNT(r.id) as totalRatings')
            ->where('r.adId = :adId')
            ->setParameter('adId', $adId)
            ->getQuery()
            ->getOneOrNullResult();

        if ($result && $result['avgRating'] !== null) {
            return round((float) $result['avgRating'], 1);
        }

        return null;
    }

    public function getTotalRatings(int $adId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.adId = :adId')
            ->setParameter('adId', $adId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
