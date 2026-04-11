<?php

namespace App\Repository;

use App\Entity\UserBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBlock::class);
    }

    public function findBlock(int $blockerId, int $blockedId): ?UserBlock
    {
        return $this->createQueryBuilder('b')
            ->where('b.blocker = :blocker AND b.blocked = :blocked')
            ->setParameter('blocker', $blockerId)
            ->setParameter('blocked', $blockedId)
            ->getQuery()->getOneOrNullResult();
    }

    /** Returns IDs of users blocked by $userId */
    public function findBlockedIds(int $userId): array
    {
        $rows = $this->createQueryBuilder('b')
            ->select('IDENTITY(b.blocked) as id')
            ->where('b.blocker = :uid')
            ->setParameter('uid', $userId)
            ->getQuery()->getArrayResult();
        return array_column($rows, 'id');
    }

    /** Returns IDs of users who blocked $userId */
    public function findBlockerIds(int $userId): array
    {
        $rows = $this->createQueryBuilder('b')
            ->select('IDENTITY(b.blocker) as id')
            ->where('b.blocked = :uid')
            ->setParameter('uid', $userId)
            ->getQuery()->getArrayResult();
        return array_column($rows, 'id');
    }

    /** Returns all IDs that should be hidden from $userId (blocked + blockers) */
    public function findHiddenIds(int $userId): array
    {
        return array_unique(array_merge(
            $this->findBlockedIds($userId),
            $this->findBlockerIds($userId)
        ));
    }
}
