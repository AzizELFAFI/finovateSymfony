<?php

namespace App\Repository;

use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    /**
     * @return Ticket[]
     */
    public function searchFilterSort(
        string $search = '',
        string $statut = '',
        string $priorite = ''
    ): array {
        $qb = $this->createQueryBuilder('t');

        if ($search) {
            $qb->andWhere('t.type LIKE :q OR t.description LIKE :q OR t.statut LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        if ($statut) {
            $qb->andWhere('t.statut = :statut')
               ->setParameter('statut', $statut);
        }

        if ($priorite) {
            $qb->andWhere('t.priorite = :priorite')
               ->setParameter('priorite', $priorite);
        }

        $qb->orderBy('t.dateCreation', 'DESC');

        return $qb->getQuery()->getResult();
    }
}