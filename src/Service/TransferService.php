<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service métier pour les transferts d'argent.
 */
class TransferService
{
    private EntityManagerInterface $entityManager;
    private TransactionManager $transactionManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        TransactionManager $transactionManager
    ) {
        $this->entityManager = $entityManager;
        $this->transactionManager = $transactionManager;
    }

    /**
     * Effectue un transfert d'argent entre deux utilisateurs.
     *
     * Règles métier:
     * 1. L'expéditeur et le bénéficiaire doivent être différents
     * 2. Le montant doit être positif
     * 3. L'expéditeur doit avoir un solde suffisant
     * 4. La limite journalière ne doit pas être dépassée (3000 TND)
     *
     * @param User $sender L'expéditeur
     * @param User $receiver Le bénéficiaire
     * @param float $amount Le montant à transférer
     * @param string $description La description du transfert
     * @return array{success: true, transaction: Transaction}|array{success: false, message: string, code: int}
     */
    public function transfer(User $sender, User $receiver, float $amount, string $description): array
    {
        // Règle 1: L'expéditeur et le bénéficiaire doivent être différents
        if ($sender->getId() === $receiver->getId()) {
            return [
                'success' => false,
                'message' => 'Vous ne pouvez pas envoyer de l\'argent à vous-même.',
                'code' => 422
            ];
        }

        // Règle 2: Le montant doit être positif
        if ($amount <= 0) {
            return [
                'success' => false,
                'message' => 'Le montant doit être supérieur à 0.',
                'code' => 422
            ];
        }

        // Règle 3: L'expéditeur doit avoir un solde suffisant
        $senderBalance = (float) str_replace(',', '.', (string) $sender->getSolde());
        if ($senderBalance < $amount) {
            return [
                'success' => false,
                'message' => 'Solde insuffisant.',
                'code' => 422
            ];
        }

        // Règle 4: Limite journalière (3000 TND)
        $sentToday = $this->getTotalSentToday((int) $sender->getId());
        $dailyLimit = 3000.0;
        
        if (($sentToday + $amount) > $dailyLimit) {
            $remaining = max(0, $dailyLimit - $sentToday);
            return [
                'success' => false,
                'message' => sprintf(
                    'Limite journalière de 3000 TND dépassée. Restant: %.2f TND.',
                    $remaining
                ),
                'code' => 422
            ];
        }

        // Effectuer le transfert
        $receiverBalance = (float) str_replace(',', '.', (string) $receiver->getSolde());
        $sender->setSolde((string) ($senderBalance - $amount));
        $receiver->setSolde((string) ($receiverBalance + $amount));

        $transaction = new Transaction();
        $transaction->setSender_id((int) $sender->getId());
        $transaction->setReceiver_id((int) $receiver->getId());
        $transaction->setAmount((string) $amount);
        $transaction->setType('TRANSFER');
        $transaction->setDescription($description);
        $transaction->setDate(new \DateTime());

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return [
            'success' => true,
            'transaction' => $transaction,
            'code' => 200
        ];
    }

    /**
     * Calcule le total envoyé aujourd'hui par un utilisateur.
     *
     * @param int $userId L'ID de l'utilisateur
     * @return float Le total envoyé aujourd'hui
     */
    public function getTotalSentToday(int $userId): float
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');
        
        $result = $this->entityManager->createQueryBuilder()
            ->select('SUM(t.amount)')
            ->from(Transaction::class, 't')
            ->where('t.sender_id = :userId')
            ->andWhere('t.date >= :today')
            ->andWhere('t.date < :tomorrow')
            ->setParameter('userId', $userId)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();
        
        return (float) ($result ?? 0);
    }

    /**
     * Vérifie si un utilisateur peut envoyer un montant donné.
     *
     * @param User $user L'utilisateur
     * @param float $amount Le montant à vérifier
     * @return array{can_send: bool, reason: string|null}
     */
    public function canSendAmount(User $user, float $amount): array
    {
        // Vérifier le solde
        $balance = (float) str_replace(',', '.', (string) $user->getSolde());
        if ($balance < $amount) {
            return [
                'can_send' => false,
                'reason' => 'Solde insuffisant.'
            ];
        }

        // Vérifier la limite journalière
        $sentToday = $this->getTotalSentToday((int) $user->getId());
        $dailyLimit = 3000.0;
        
        if (($sentToday + $amount) > $dailyLimit) {
            return [
                'can_send' => false,
                'reason' => sprintf(
                    'Limite journalière dépassée. Restant: %.2f TND.',
                    max(0, $dailyLimit - $sentToday)
                )
            ];
        }

        return [
            'can_send' => true,
            'reason' => null
        ];
    }

    /**
     * Calcule le solde disponible pour envoi aujourd'hui.
     *
     * @param User $user L'utilisateur
     * @return float Le montant maximum envoyable aujourd'hui
     */
    public function getRemainingDailyLimit(User $user): float
    {
        $sentToday = $this->getTotalSentToday((int) $user->getId());
        $dailyLimit = 3000.0;
        $balance = (float) str_replace(',', '.', (string) $user->getSolde());
        
        return min($dailyLimit - $sentToday, $balance);
    }
}
