<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @return Message[]
     */
    public function searchFilterSort(
        string $search = '',
        string $senderRole = '',
        string $sortBy = 'sentAt',
        string $sortDir = 'DESC'
    ): array {
        $qb = $this->createQueryBuilder('m');

        if ($search) {
            $qb->andWhere('m.content LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        if ($senderRole) {
            $qb->andWhere('m.senderRole = :role')
               ->setParameter('role', $senderRole);
        }

        $allowedSort = ['sentAt', 'senderRole', 'id'];
        $allowedDir  = ['ASC', 'DESC'];

        $sortBy  = in_array($sortBy, $allowedSort) ? $sortBy : 'sentAt';
        $sortDir = in_array(strtoupper($sortDir), $allowedDir) ? strtoupper($sortDir) : 'DESC';

        $qb->orderBy('m.' . $sortBy, $sortDir);

        return $qb->getQuery()->getResult();
    }
}