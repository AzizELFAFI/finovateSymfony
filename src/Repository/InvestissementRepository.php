<?php

namespace App\Repository;

use App\Entity\Investissement;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Investissement>
 */
class InvestissementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Investissement::class);
    }

    public function findOnePendingByUserAndProject(User $user, Project $project): ?Investissement
    {
        return $this->findOneBy([
            'user' => $user,
            'project' => $project,
            'status' => 'PENDING',
        ]);
    }

    /**
     * @return Investissement[]
     */
    public function findPendingByProject(Project $project): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.project = :p')
            ->andWhere('i.status = :st')
            ->setParameter('p', $project)
            ->setParameter('st', 'PENDING')
            ->orderBy('i.investment_date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Investissement[]
     */
    public function findByInvestorOrdered(User $user): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.user = :u')
            ->setParameter('u', $user)
            ->leftJoin('i.project', 'pr')->addSelect('pr')
            ->orderBy('i.investment_date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
