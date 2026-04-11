<?php

namespace App\Repository;

use App\Entity\UserRestriction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRestrictionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserRestriction::class);
    }

    /**
     * Returns the currently active restriction for a user, or null if none.
     */
    public function findActiveForUser(int $userId): ?UserRestriction
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :uid')
            ->andWhere('r.active = true')
            ->setParameter('uid', $userId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns all restrictions for a user, newest first.
     */
    public function findAllForUser(int $userId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :uid')
            ->setParameter('uid', $userId)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
