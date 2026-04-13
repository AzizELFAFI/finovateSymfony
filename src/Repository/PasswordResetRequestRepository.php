<?php

namespace App\Repository;

use App\Entity\PasswordResetRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetRequest>
 */
class PasswordResetRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetRequest::class);
    }

    public function findValidByTokenHash(string $tokenHash, \DateTimeInterface $now): ?PasswordResetRequest
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.tokenHash = :tokenHash')
            ->andWhere('r.expiresAt > :now')
            ->andWhere('r.usedAt IS NULL')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
