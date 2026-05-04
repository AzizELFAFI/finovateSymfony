<?php

namespace App\Service;

use App\Entity\Transaction;

/**
 * Service métier pour la validation des règles métier de l'entité Transaction.
 */
class TransactionManager
{
    /**
     * Valide les règles métier d'une transaction.
     *
     * Règles validées:
     * 1. Le montant doit être supérieur à 0
     * 2. Le type est obligatoire
     * 3. La description est obligatoire
     *
     * @param Transaction $transaction La transaction à valider
     * @return bool True si la transaction est valide
     * @throws \InvalidArgumentException Si une règle métier n'est pas respectée
     */
    public function validate(Transaction $transaction): bool
    {
        // Règle 1: Le montant doit être supérieur à 0
        $amount = (float) $transaction->getAmount();
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant doit être supérieur à 0.');
        }

        // Règle 2: Le type est obligatoire
        $type = trim($transaction->getType());
        if (empty($type)) {
            throw new \InvalidArgumentException('Le type de transaction est obligatoire.');
        }

        // Règle 3: La description est obligatoire
        $description = trim($transaction->getDescription());
        if (empty($description)) {
            throw new \InvalidArgumentException('La description est obligatoire.');
        }

        return true;
    }

    /**
     * Vérifie si le montant est valide (supérieur à 0).
     *
     * @param Transaction $transaction La transaction à vérifier
     * @return bool True si le montant est valide
     */
    public function isAmountValid(Transaction $transaction): bool
    {
        $amount = (float) $transaction->getAmount();
        return $amount > 0;
    }

    /**
     * Vérifie si le type est valide.
     *
     * @param Transaction $transaction La transaction à vérifier
     * @return bool True si le type est valide
     */
    public function isTypeValid(Transaction $transaction): bool
    {
        $type = trim($transaction->getType());
        return !empty($type);
    }

    /**
     * Vérifie si la description est valide.
     *
     * @param Transaction $transaction La transaction à vérifier
     * @return bool True si la description est valide
     */
    public function isDescriptionValid(Transaction $transaction): bool
    {
        $description = trim($transaction->getDescription());
        return !empty($description);
    }

    /**
     * Vérifie si l'expéditeur et le bénéficiaire sont différents.
     *
     * @param Transaction $transaction La transaction à vérifier
     * @return bool True si l'expéditeur et le bénéficiaire sont différents
     */
    public function areSenderAndReceiverDifferent(Transaction $transaction): bool
    {
        return $transaction->getSender_id() !== $transaction->getReceiver_id();
    }
}
