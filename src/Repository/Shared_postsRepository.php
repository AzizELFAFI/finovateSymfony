<?php

namespace App\Repository;

use App\Entity\Shared_posts;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Shared_postsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shared_posts::class);
    }

    // Add custom methods as needed
}