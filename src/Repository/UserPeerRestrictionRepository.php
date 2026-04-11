<?php

namespace App\Repository;

use App\Entity\UserPeerRestriction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserPeerRestrictionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPeerRestriction::class);
    }

    public function findActive(int $restrictorId, int $restrictedId): ?UserPeerRestriction
    {
        return $this->createQueryBuilder('r')
            ->where('r.restrictor = :rid AND r.restricted = :uid AND r.active = true')
            ->setParameter('rid', $restrictorId)
            ->setParameter('uid', $restrictedId)
            ->getQuery()->getOneOrNullResult();
    }

    /** IDs restricted by $userId (they can see but not interact) */
    public function findRestrictedIds(int $userId): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.restricted) as id')
            ->where('r.restrictor = :uid AND r.active = true')
            ->setParameter('uid', $userId)
            ->getQuery()->getArrayResult();
        return array_column($rows, 'id');
    }
}
