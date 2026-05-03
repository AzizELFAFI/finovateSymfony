<?php

namespace App\Service;

use App\Entity\Project;

/**
 * Service métier pour la validation des règles métier de l'entité Project.
 *
 * Règles métier validées :
 * 1. Le titre doit contenir au moins 3 caractères
 * 2. Le montant objectif (goal_amount) doit être strictement positif
 * 3. Le statut doit être l'un des statuts autorisés : open, funded, closed, cancelled
 * 4. La date limite (deadline) doit être dans le futur
 * 5. Le montant collecté (current_amount) ne peut pas dépasser le montant objectif
 */
class ProjectManager
{
    /** @var string[] */
    private const ALLOWED_STATUSES = ['open', 'funded', 'closed', 'cancelled'];

    /**
     * Valide les règles métier d'un projet.
     *
     * @param Project $project Le projet à valider
     * @return bool True si le projet est valide
     * @throws \InvalidArgumentException Si une règle métier n'est pas respectée
     */
    public function validate(Project $project): bool
    {
        // Règle 1 : Le titre doit contenir au moins 3 caractères
        if (strlen((string) $project->getTitle()) < 3) {
            throw new \InvalidArgumentException('Le titre du projet doit contenir au moins 3 caractères.');
        }

        // Règle 2 : Le montant objectif doit être strictement positif
        if ((float) $project->getGoalAmount() <= 0) {
            throw new \InvalidArgumentException('Le montant objectif doit être strictement positif.');
        }

        // Règle 3 : Le statut doit être valide
        $status = $project->getStatus();
        if ($status !== null && !in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new \InvalidArgumentException(
                'Le statut doit être l\'un des suivants : ' . implode(', ', self::ALLOWED_STATUSES) . '.'
            );
        }

        // Règle 4 : La deadline doit être dans le futur
        $deadline = $project->getDeadline();
        if ($deadline !== null && $deadline <= new \DateTime()) {
            throw new \InvalidArgumentException('La date limite du projet doit être dans le futur.');
        }

        // Règle 5 : Le montant collecté ne peut pas dépasser le montant objectif
        $currentAmount = $project->getCurrentAmount();
        if ($currentAmount !== null && (float) $currentAmount > (float) $project->getGoalAmount()) {
            throw new \InvalidArgumentException('Le montant collecté ne peut pas dépasser le montant objectif.');
        }

        return true;
    }

    /**
     * Vérifie si le titre est valide (au moins 3 caractères).
     *
     * @param Project $project
     * @return bool
     */
    public function isTitleValid(Project $project): bool
    {
        return strlen((string) $project->getTitle()) >= 3;
    }

    /**
     * Vérifie si le montant objectif est valide (strictement positif).
     *
     * @param Project $project
     * @return bool
     */
    public function isGoalAmountValid(Project $project): bool
    {
        return (float) $project->getGoalAmount() > 0;
    }

    /**
     * Vérifie si le statut est valide.
     *
     * @param Project $project
     * @return bool
     */
    public function isStatusValid(Project $project): bool
    {
        $status = $project->getStatus();
        if ($status === null) {
            return true; // null est autorisé
        }

        return in_array($status, self::ALLOWED_STATUSES, true);
    }

    /**
     * Vérifie si la deadline est dans le futur.
     *
     * @param Project $project
     * @return bool
     */
    public function isDeadlineValid(Project $project): bool
    {
        $deadline = $project->getDeadline();
        if ($deadline === null) {
            return true;
        }

        return $deadline > new \DateTime();
    }

    /**
     * Vérifie si le montant collecté ne dépasse pas le montant objectif.
     *
     * @param Project $project
     * @return bool
     */
    public function isCurrentAmountValid(Project $project): bool
    {
        $currentAmount = $project->getCurrentAmount();
        if ($currentAmount === null) {
            return true;
        }

        return (float) $currentAmount <= (float) $project->getGoalAmount();
    }
}
