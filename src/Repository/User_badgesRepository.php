<?php

namespace App\Repository;

use App\Entity\User_badges;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class User_badgesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User_badges::class);
    }

    // Add custom methods as needed
}