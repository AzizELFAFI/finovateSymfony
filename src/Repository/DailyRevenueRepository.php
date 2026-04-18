<?php

namespace App\Repository;

use App\Entity\DailyRevenue;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DailyRevenue>
 */
class DailyRevenueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyRevenue::class);
    }

    public function findOneByProjectAndDate(Project $project, \DateTimeImmutable $day): ?DailyRevenue
    {
        return $this->findOneBy([
            'project' => $project,
            'revenue_date' => $day,
        ]);
    }

    public function findLatestRevenueDate(Project $project): ?\DateTimeImmutable
    {
        $max = $this->createQueryBuilder('d')
            ->select('MAX(d.revenue_date)')
            ->andWhere('d.project = :p')
            ->setParameter('p', $project)
            ->getQuery()
            ->getSingleScalarResult();

        if ($max === null) {
            return null;
        }

        if ($max instanceof \DateTimeImmutable) {
            return $max->setTime(0, 0, 0);
        }

        if ($max instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($max)->setTime(0, 0, 0);
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $max);

        return $parsed !== false ? $parsed : null;
    }
}
