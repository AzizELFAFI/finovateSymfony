<?php

namespace App\Repository;

use App\Entity\Alert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alert::class);
    }

    public function findForUser(int $userId, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.user = :uid')
            ->setParameter('uid', $userId)
            ->orderBy('a.createdAt', 'DESC');

        if ($type) {
            $qb->andWhere('a.type = :type')->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    public function countUnread(int $userId): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user = :uid AND a.isRead = false')
            ->setParameter('uid', $userId)
            ->getQuery()->getSingleScalarResult();
    }
}
