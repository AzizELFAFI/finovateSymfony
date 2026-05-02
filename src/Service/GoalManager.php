<?php

namespace App\Service;

use App\Entity\Goal;

/**
 * Service métier pour la validation des règles métier de l'entité Goal.
 */
class GoalManager
{
    /**
     * Valide les règles métier d'un objectif.
     *
     * Règles validées:
     * 1. Le titre doit contenir entre 3 et 40 caractères
     * 2. La deadline doit être dans le futur
     * 3. Le montant objectif doit être supérieur à 0
     * 4. Le montant actuel doit être >= 0
     *
     * @param Goal $goal L'objectif à valider
     * @return bool True si l'objectif est valide
     * @throws \InvalidArgumentException Si une règle métier n'est pas respectée
     */
    public function validate(Goal $goal): bool
    {
        // Règle 1: Le titre doit contenir entre 3 et 40 caractères
        $title = trim($goal->getTitle());
        $titleLength = mb_strlen($title);
        if ($titleLength < 3 || $titleLength > 40) {
            throw new \InvalidArgumentException('Le titre doit contenir entre 3 et 40 caractères.');
        }

        // Règle 2: La deadline doit être dans le futur
        $deadline = $goal->getDeadline();
        $today = new \DateTime('today');
        if ($deadline <= $today) {
            throw new \InvalidArgumentException('La deadline doit être dans le futur.');
        }

        // Règle 3: Le montant objectif doit être supérieur à 0
        $targetAmount = (float) $goal->getTarget_amount();
        if ($targetAmount <= 0) {
            throw new \InvalidArgumentException('Le montant objectif doit être supérieur à 0.');
        }

        // Règle 4: Le montant actuel doit être >= 0
        $currentAmount = (float) $goal->getCurrent_amount();
        if ($currentAmount < 0) {
            throw new \InvalidArgumentException('Le montant actuel ne peut pas être négatif.');
        }

        return true;
    }

    /**
     * Vérifie si le titre est valide (3-40 caractères).
     *
     * @param Goal $goal L'objectif à vérifier
     * @return bool True si le titre est valide
     */
    public function isTitleValid(Goal $goal): bool
    {
        $title = trim($goal->getTitle());
        $length = mb_strlen($title);
        return $length >= 3 && $length <= 40;
    }

    /**
     * Vérifie si la deadline est dans le futur.
     *
     * @param Goal $goal L'objectif à vérifier
     * @return bool True si la deadline est dans le futur
     */
    public function isDeadlineInFuture(Goal $goal): bool
    {
        $deadline = $goal->getDeadline();
        $today = new \DateTime('today');
        return $deadline > $today;
    }

    /**
     * Vérifie si le montant objectif est valide (> 0).
     *
     * @param Goal $goal L'objectif à vérifier
     * @return bool True si le montant objectif est valide
     */
    public function isTargetAmountValid(Goal $goal): bool
    {
        $amount = (float) $goal->getTarget_amount();
        return $amount > 0;
    }

    /**
     * Vérifie si le montant actuel est valide (>= 0).
     *
     * @param Goal $goal L'objectif à vérifier
     * @return bool True si le montant actuel est valide
     */
    public function isCurrentAmountValid(Goal $goal): bool
    {
        $amount = (float) $goal->getCurrent_amount();
        return $amount >= 0;
    }

    /**
     * Calcule le pourcentage de progression de l'objectif.
     *
     * @param Goal $goal L'objectif
     * @return float Le pourcentage de progression (0-100)
     */
    public function getProgressPercentage(Goal $goal): float
    {
        $target = (float) $goal->getTarget_amount();
        $current = (float) $goal->getCurrent_amount();
        
        if ($target <= 0) {
            return 0;
        }
        
        $percentage = ($current / $target) * 100;
        return min(100, max(0, $percentage));
    }

    /**
     * Vérifie si l'objectif est atteint.
     *
     * @param Goal $goal L'objectif à vérifier
     * @return bool True si l'objectif est atteint
     */
    public function isGoalReached(Goal $goal): bool
    {
        $target = (float) $goal->getTarget_amount();
        $current = (float) $goal->getCurrent_amount();
        
        return $current >= $target;
    }

    /**
     * Vérifie si l'objectif est en retard (deadline dépassée).
     *
     * @param Goal $goal L'objectif à vérifier
     * @return bool True si l'objectif est en retard
     */
    public function isGoalOverdue(Goal $goal): bool
    {
        $deadline = $goal->getDeadline();
        $today = new \DateTime('today');
        
        return $deadline < $today && !$this->isGoalReached($goal);
    }
}
