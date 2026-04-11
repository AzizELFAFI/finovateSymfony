<?php

namespace App\Repository;

use App\Entity\UserReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserReport::class);
    }

    public function findUntreated(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.treated = false')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function countUntreated(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.treated = false')
            ->getQuery()->getSingleScalarResult();
    }
}
