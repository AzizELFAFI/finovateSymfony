<?php

namespace App\Service;

use App\Entity\Ad;

/**
 * Service métier pour la validation des règles métier de l'entité Ad.
 */
class AdManager
{
    /**
     * Valide les règles métier d'une annonce.
     *
     * Règles validées:
     * 1. Le titre est obligatoire
     * 2. La durée doit être positive
     * 3. Les points de récompense doivent être >= 0
     *
     * @param Ad $ad L'annonce à valider
     * @return bool True si l'annonce est valide
     * @throws \InvalidArgumentException Si une règle métier n'est pas respectée
     */
    public function validate(Ad $ad): bool
    {
        // Règle 1: Le titre est obligatoire
        $title = trim($ad->getTitle() ?? '');
        if (empty($title)) {
            throw new \InvalidArgumentException('Le titre de l\'annonce est obligatoire.');
        }

        // Règle 2: La durée doit être positive
        $duration = $ad->getDuration();
        if ($duration === null || $duration <= 0) {
            throw new \InvalidArgumentException('La durée doit être supérieure à 0.');
        }

        // Règle 3: Les points de récompense doivent être >= 0
        $rewardPoints = $ad->getRewardPoints();
        if ($rewardPoints === null || $rewardPoints < 0) {
            throw new \InvalidArgumentException('Les points de récompense ne peuvent pas être négatifs.');
        }

        return true;
    }

    /**
     * Vérifie si le titre est valide.
     *
     * @param Ad $ad L'annonce à vérifier
     * @return bool True si le titre est valide
     */
    public function isTitleValid(Ad $ad): bool
    {
        $title = trim($ad->getTitle() ?? '');
        return !empty($title);
    }

    /**
     * Vérifie si la durée est valide (> 0).
     *
     * @param Ad $ad L'annonce à vérifier
     * @return bool True si la durée est valide
     */
    public function isDurationValid(Ad $ad): bool
    {
        $duration = $ad->getDuration();
        return $duration !== null && $duration > 0;
    }

    /**
     * Vérifie si les points de récompense sont valides (>= 0).
     *
     * @param Ad $ad L'annonce à vérifier
     * @return bool True si les points sont valides
     */
    public function isRewardPointsValid(Ad $ad): bool
    {
        $rewardPoints = $ad->getRewardPoints();
        return $rewardPoints !== null && $rewardPoints >= 0;
    }

    /**
     * Vérifie si l'annonce offre des points de récompense.
     *
     * @param Ad $ad L'annonce à vérifier
     * @return bool True si l'annonce offre des points
     */
    public function hasReward(Ad $ad): bool
    {
        $rewardPoints = $ad->getRewardPoints();
        return $rewardPoints !== null && $rewardPoints > 0;
    }

    /**
     * Calcule le nombre total de clics sur l'annonce.
     *
     * @param Ad $ad L'annonce
     * @return int Le nombre de clics
     */
    public function getClickCount(Ad $ad): int
    {
        return $ad->getUserAdClicks()->count();
    }

    /**
     * Calcule le taux de conversion (clics avec récompense / total clics).
     *
     * @param Ad $ad L'annonce
     * @return float Le taux de conversion (0-100)
     */
    public function getConversionRate(Ad $ad): float
    {
        $clicks = $ad->getUserAdClicks();
        if ($clicks->isEmpty()) {
            return 0.0;
        }

        $rewardedClicks = 0;
        foreach ($clicks as $click) {
            if ($click->isRewarded()) {
                $rewardedClicks++;
            }
        }

        return ($rewardedClicks / $clicks->count()) * 100;
    }

    /**
     * Vérifie si un utilisateur a déjà cliqué sur l'annonce.
     *
     * @param Ad $ad L'annonce
     * @param int $userId L'ID de l'utilisateur
     * @return bool True si l'utilisateur a déjà cliqué
     */
    public function hasUserClicked(Ad $ad, int $userId): bool
    {
        foreach ($ad->getUserAdClicks() as $click) {
            $clickUserId = $click->getUser()?->getId();
            if ($clickUserId !== null && (int) $clickUserId === $userId) {
                return true;
            }
        }

        return false;
    }
}
