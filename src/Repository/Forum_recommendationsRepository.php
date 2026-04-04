<?php

namespace App\Repository;

use App\Entity\Forum_recommendations;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Forum_recommendationsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Forum_recommendations::class);
    }

    // Add custom methods as needed
}