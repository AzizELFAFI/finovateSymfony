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

    /**
     * Get transactions for a user by period
     * @param int $userId User ID
     * @param string $period 'day', 'month', '3months', 'year'
     * @return array Transactions with direction info
     */
    public function getTransactionsByPeriod(int $userId, string $period): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $startDate = $this->getStartDate($period);
        
        // Get sent transactions
        $sentSql = 'SELECT id, sender_id, receiver_id, amount, type, description, date FROM transaction 
                    WHERE sender_id = :userId AND date >= :startDate 
                    ORDER BY date DESC';
        $sentRows = $conn->fetchAllAssociative($sentSql, [
            'userId' => $userId,
            'startDate' => $startDate,
        ]);
        
        // Get received transactions
        $receivedSql = 'SELECT id, sender_id, receiver_id, amount, type, description, date FROM transaction 
                        WHERE receiver_id = :userId AND date >= :startDate 
                        ORDER BY date DESC';
        $receivedRows = $conn->fetchAllAssociative($receivedSql, [
            'userId' => $userId,
            'startDate' => $startDate,
        ]);
        
        $transactions = [];
        
        foreach ($sentRows as $row) {
            $transactions[] = [
                'id' => $row['id'],
                'amount' => $row['amount'],
                'type' => $row['type'],
                'description' => $row['description'],
                'date' => (new \DateTime($row['date']))->format('d/m/Y H:i'),
                'direction' => 'sent',
            ];
        }
        
        foreach ($receivedRows as $row) {
            $transactions[] = [
                'id' => $row['id'],
                'amount' => $row['amount'],
                'type' => $row['type'],
                'description' => $row['description'],
                'date' => (new \DateTime($row['date']))->format('d/m/Y H:i'),
                'direction' => 'received',
            ];
        }
        
        // Sort by date descending
        usort($transactions, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });
        
        return $transactions;
    }

    private function getStartDate(string $period): string
    {
        $now = new \DateTime();
        
        return match ($period) {
            'day' => (clone $now)->modify('today')->format('Y-m-d H:i:s'),
            'month' => (clone $now)->modify('first day of this month 00:00:00')->format('Y-m-d H:i:s'),
            '3months' => (clone $now)->modify('-3 months')->format('Y-m-d H:i:s'),
            'year' => (clone $now)->modify('first day of January this year 00:00:00')->format('Y-m-d H:i:s'),
            default => (clone $now)->modify('today')->format('Y-m-d H:i:s'),
        };
    }
}