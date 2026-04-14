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

    /**
     * Get transaction statistics for a user
     */
    public function getUserStats(int $userId): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        // Total sent today
        $sentToday = (float) $conn->fetchOne(
            'SELECT COALESCE(SUM(CAST(amount AS DECIMAL(18,2))), 0) FROM transaction WHERE sender_id = ? AND date >= ?',
            [$userId, (new \DateTime('today'))->format('Y-m-d H:i:s')]
        );

        // Total sent this month
        $firstDayOfMonth = new \DateTime('first day of this month 00:00:00');
        $sentThisMonth = (float) $conn->fetchOne(
            'SELECT COALESCE(SUM(CAST(amount AS DECIMAL(18,2))), 0) FROM transaction WHERE sender_id = ? AND date >= ?',
            [$userId, $firstDayOfMonth->format('Y-m-d H:i:s')]
        );

        // Count of sent transactions
        $sentCount = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM transaction WHERE sender_id = ?',
            [$userId]
        );

        // Count of received transactions
        $receivedCount = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM transaction WHERE receiver_id = ?',
            [$userId]
        );

        // Total received
        $totalReceived = (float) $conn->fetchOne(
            'SELECT COALESCE(SUM(CAST(amount AS DECIMAL(18,2))), 0) FROM transaction WHERE receiver_id = ?',
            [$userId]
        );

        return [
            'sent_today' => $sentToday,
            'sent_this_month' => $sentThisMonth,
            'sent_count' => $sentCount,
            'received_count' => $receivedCount,
            'total_received' => $totalReceived,
            'daily_limit' => 3000.0,
            'daily_remaining' => max(0, 3000.0 - $sentToday),
        ];
    }
}