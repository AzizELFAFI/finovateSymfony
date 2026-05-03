<?php

namespace App\Service;

use App\Entity\Investissement;

/**
 * Service métier pour la validation des règles métier de l'entité Investissement.
 *
 * Règles métier validées :
 * 1. Le montant doit être strictement positif
 * 2. Le statut doit être l'un des statuts autorisés : pending, active, completed, cancelled
 * 3. Le pourcentage de revenu doit être compris entre 0 et 100 (si renseigné)
 * 4. La date d'investissement ne peut pas être dans le futur
 */
class InvestissementManager
{
    /** @var string[] */
    private const ALLOWED_STATUSES = ['pending', 'active', 'completed', 'cancelled'];

    /**
     * Valide les règles métier d'un investissement.
     *
     * @param Investissement $investissement L'investissement à valider
     * @return bool True si l'investissement est valide
     * @throws \InvalidArgumentException Si une règle métier n'est pas respectée
     */
    public function validate(Investissement $investissement): bool
    {
        // Règle 1 : Le montant doit être strictement positif
        if ((float) $investissement->getAmount() <= 0) {
            throw new \InvalidArgumentException('Le montant de l\'investissement doit être strictement positif.');
        }

        // Règle 2 : Le statut doit être valide
        if (!in_array($investissement->getStatus(), self::ALLOWED_STATUSES, true)) {
            throw new \InvalidArgumentException(
                'Le statut doit être l\'un des suivants : ' . implode(', ', self::ALLOWED_STATUSES) . '.'
            );
        }

        // Règle 3 : Le pourcentage de revenu doit être entre 0 et 100
        $revenuePercentage = $investissement->getRevenuePercentage();
        if ($revenuePercentage !== null && ($revenuePercentage < 0 || $revenuePercentage > 100)) {
            throw new \InvalidArgumentException('Le pourcentage de revenu doit être compris entre 0 et 100.');
        }

        // Règle 4 : La date d'investissement ne peut pas être dans le futur
        $investmentDate = $investissement->getInvestmentDate();
        if ($investmentDate !== null && $investmentDate > new \DateTimeImmutable()) {
            throw new \InvalidArgumentException('La date d\'investissement ne peut pas être dans le futur.');
        }

        return true;
    }

    /**
     * Vérifie si le montant est valide (strictement positif).
     *
     * @param Investissement $investissement
     * @return bool
     */
    public function isAmountValid(Investissement $investissement): bool
    {
        return (float) $investissement->getAmount() > 0;
    }

    /**
     * Vérifie si le statut est valide.
     *
     * @param Investissement $investissement
     * @return bool
     */
    public function isStatusValid(Investissement $investissement): bool
    {
        return in_array($investissement->getStatus(), self::ALLOWED_STATUSES, true);
    }

    /**
     * Vérifie si le pourcentage de revenu est valide (entre 0 et 100).
     *
     * @param Investissement $investissement
     * @return bool
     */
    public function isRevenuePercentageValid(Investissement $investissement): bool
    {
        $revenuePercentage = $investissement->getRevenuePercentage();
        if ($revenuePercentage === null) {
            return true; // null est autorisé
        }

        return $revenuePercentage >= 0 && $revenuePercentage <= 100;
    }

    /**
     * Vérifie si la date d'investissement n'est pas dans le futur.
     *
     * @param Investissement $investissement
     * @return bool
     */
    public function isDateValid(Investissement $investissement): bool
    {
        $investmentDate = $investissement->getInvestmentDate();
        if ($investmentDate === null) {
            return true;
        }

        return $investmentDate <= new \DateTimeImmutable();
    }
}
