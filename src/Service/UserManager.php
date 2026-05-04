<?php

namespace App\Service;

use App\Entity\User;

/**
 * Service métier pour la validation des règles métier de l'entité User.
 */
class UserManager
{
    /**
     * Valide les règles métier d'un utilisateur.
     * 
     * Règles validées:
     * 1. Le mot de passe doit contenir au moins 8 caractères
     * 2. L'utilisateur doit avoir au moins 18 ans
     * 3. Le CIN doit contenir exactement 8 chiffres
     *
     * @param User $user L'utilisateur à valider
     * @return bool True si l'utilisateur est valide
     * @throws \InvalidArgumentException Si une règle métier n'est pas respectée
     */
    public function validate(User $user): bool
    {
        // Règle 1: Le mot de passe doit contenir au moins 8 caractères
        if (strlen($user->getPassword()) < 8) {
            throw new \InvalidArgumentException('Le mot de passe doit contenir au moins 8 caractères.');
        }

        // Règle 2: L'utilisateur doit avoir au moins 18 ans
        $today = new \DateTime();
        $birthdate = $user->getBirthdate();
        $age = $today->diff($birthdate)->y;
        
        if ($age < 18) {
            throw new \InvalidArgumentException('L\'utilisateur doit avoir au moins 18 ans.');
        }

        // Règle 3: Le CIN doit contenir exactement 8 chiffres
        $cin = $user->getCin();
        if (!preg_match('/^\d{8}$/', $cin)) {
            throw new \InvalidArgumentException('Le CIN doit contenir exactement 8 chiffres.');
        }

        return true;
    }

    /**
     * Vérifie si un utilisateur est majeur.
     *
     * @param User $user L'utilisateur à vérifier
     * @return bool True si l'utilisateur est majeur
     */
    public function isMajeur(User $user): bool
    {
        $today = new \DateTime();
        $birthdate = $user->getBirthdate();
        $age = $today->diff($birthdate)->y;
        
        return $age >= 18;
    }

    /**
     * Vérifie si le mot de passe est sécurisé (au moins 8 caractères).
     *
     * @param User $user L'utilisateur à vérifier
     * @return bool True si le mot de passe est sécurisé
     */
    public function isPasswordSecure(User $user): bool
    {
        return strlen($user->getPassword()) >= 8;
    }

    /**
     * Vérifie si le CIN est valide (exactement 8 chiffres).
     *
     * @param User $user L'utilisateur à vérifier
     * @return bool True si le CIN est valide
     */
    public function isCinValid(User $user): bool
    {
        return preg_match('/^\d{8}$/', $user->getCin()) === 1;
    }
}
