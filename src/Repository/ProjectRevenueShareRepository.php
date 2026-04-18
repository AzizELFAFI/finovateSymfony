<?php

namespace App\Repository;

use App\Entity\Investissement;
use App\Entity\Project;
use App\Entity\ProjectRevenueShare;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectRevenueShare>
 */
class ProjectRevenueShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectRevenueShare::class);
    }

    /**
     * @return ProjectRevenueShare[]
     */
    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.project = :p')
            ->setParameter('p', $project)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ProjectRevenueShare[]
     */
    public function findByInvestorWithDetails(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :u')
            ->setParameter('u', $user)
            ->innerJoin('s.project', 'pr')->addSelect('pr')
            ->innerJoin('s.investissement', 'inv')->addSelect('inv')
            ->orderBy('pr.title', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByInvestissement(Investissement $investissement): ?ProjectRevenueShare
    {
        return $this->findOneBy(['investissement' => $investissement]);
    }
}
