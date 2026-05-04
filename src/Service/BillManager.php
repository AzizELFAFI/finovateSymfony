<?php

namespace App\Service;

use App\Entity\Bill;

/**
 * Service métier pour la validation des règles métier de l'entité Bill.
 */
class BillManager
{
    /**
     * Valide les règles métier d'une facture.
     *
     * Règles validées:
     * 1. La référence doit contenir entre 3 et 50 caractères
     * 2. Le montant doit être supérieur à 0
     *
     * @param Bill $bill La facture à valider
     * @return bool True si la facture est valide
     * @throws \InvalidArgumentException Si une règle métier n'est pas respectée
     */
    public function validate(Bill $bill): bool
    {
        // Règle 1: La référence doit contenir entre 3 et 50 caractères
        $reference = trim($bill->getReference());
        $referenceLength = mb_strlen($reference);
        if ($referenceLength < 3 || $referenceLength > 50) {
            throw new \InvalidArgumentException('La référence doit contenir entre 3 et 50 caractères.');
        }

        // Règle 2: Le montant doit être supérieur à 0
        $amount = (float) $bill->getAmount();
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant doit être supérieur à 0.');
        }

        return true;
    }

    /**
     * Vérifie si la référence est valide (3-50 caractères).
     *
     * @param Bill $bill La facture à vérifier
     * @return bool True si la référence est valide
     */
    public function isReferenceValid(Bill $bill): bool
    {
        $reference = trim($bill->getReference());
        $length = mb_strlen($reference);
        return $length >= 3 && $length <= 50;
    }

    /**
     * Vérifie si le montant est valide (> 0).
     *
     * @param Bill $bill La facture à vérifier
     * @return bool True si le montant est valide
     */
    public function isAmountValid(Bill $bill): bool
    {
        $amount = (float) $bill->getAmount();
        return $amount > 0;
    }

    /**
     * Vérifie si la date de paiement est dans le futur.
     *
     * @param Bill $bill La facture à vérifier
     * @return bool True si la date de paiement est dans le futur
     */
    public function isPaymentDateInFuture(Bill $bill): bool
    {
        $paymentDate = $bill->getDate_paiement();
        $today = new \DateTime('today');
        return $paymentDate > $today;
    }

    /**
     * Vérifie si la date de paiement est aujourd'hui.
     *
     * @param Bill $bill La facture à vérifier
     * @return bool True si la date de paiement est aujourd'hui
     */
    public function isPaymentDateToday(Bill $bill): bool
    {
        $paymentDate = $bill->getDate_paiement();
        $today = new \DateTime('today');
        return $paymentDate->format('Y-m-d') === $today->format('Y-m-d');
    }

    /**
     * Vérifie si la facture est en retard (date de paiement dépassée).
     *
     * @param Bill $bill La facture à vérifier
     * @return bool True si la facture est en retard
     */
    public function isBillOverdue(Bill $bill): bool
    {
        $paymentDate = $bill->getDate_paiement();
        $today = new \DateTime('today');
        return $paymentDate < $today;
    }
}
