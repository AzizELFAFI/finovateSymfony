<?php

namespace App\Repository;

use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * Get total amount sent by a user today (for daily limit check)
     */
    public function getTotalSentToday(int $senderId): float
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT SUM(CAST(amount AS DECIMAL(18,2))) FROM transaction WHERE sender_id = :senderId AND date >= :today AND date < :tomorrow';
        
        $result = $conn->fetchOne($sql, [
            'senderId' => $senderId,
            'today' => (new \DateTime('today'))->format('Y-m-d H:i:s'),
            'tomorrow' => (new \DateTime('tomorrow'))->format('Y-m-d H:i:s'),
        ]);

        return (float) ($result ?: 0);
    }
}