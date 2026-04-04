<?php

namespace App\Repository;

use App\Entity\Badge_types;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Badge_typesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Badge_types::class);
    }

    // Add custom methods as needed
}