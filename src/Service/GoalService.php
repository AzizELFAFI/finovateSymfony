<?php

namespace App\Service;

use App\Entity\Goal;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service métier pour la gestion des objectifs d'épargne.
 */
class GoalService
{
    private EntityManagerInterface $entityManager;
    private GoalManager $goalManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        GoalManager $goalManager
    ) {
        $this->entityManager = $entityManager;
        $this->goalManager = $goalManager;
    }

    /**
     * Crée un nouvel objectif d'épargne.
     *
     * @param User $user L'utilisateur
     * @param string $title Le titre de l'objectif
     * @param float $targetAmount Le montant objectif
     * @param \DateTimeInterface $deadline La date limite
     * @return array{success: true, goal: Goal}|array{success: false, message: string}
     */
    public function createGoal(User $user, string $title, float $targetAmount, \DateTimeInterface $deadline): array
    {
        $goal = new Goal();
        $goal->setId($this->generateUniqueId());
        $goal->setId_user((int) $user->getId());
        $goal->setTitle($title);
        $goal->setDeadline($deadline);
        $goal->setCreated_at(new \DateTime());
        $goal->setStatus('IN_PROGRESS');
        $goal->setTarget_amount((string) $targetAmount);
        $goal->setCurrent_amount('0');

        // Valider les règles métier
        try {
            $this->goalManager->validate($goal);
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }

        $this->entityManager->persist($goal);
        $this->entityManager->flush();

        return [
            'success' => true,
            'goal' => $goal
        ];
    }

    /**
     * Ajoute un montant à un objectif.
     *
     * Règles métier:
     * 1. L'utilisateur doit être propriétaire de l'objectif
     * 2. L'objectif ne doit pas être déjà terminé
     * 3. L'utilisateur doit avoir un solde suffisant
     *
     * @param Goal $goal L'objectif
     * @param User $user L'utilisateur
     * @param float $amount Le montant à ajouter
     * @return array{success: true, completed: bool}|array{success: false, message: string}
     */
    public function addAmount(Goal $goal, User $user, float $amount): array
    {
        // Règle 1: L'utilisateur doit être propriétaire
        if ((int) $goal->getId_user() !== (int) $user->getId()) {
            return [
                'success' => false,
                'message' => 'Vous n\'êtes pas propriétaire de cet objectif.'
            ];
        }

        // Règle 2: L'objectif ne doit pas être terminé
        if ($goal->getStatus() === 'COMPLETED') {
            return [
                'success' => false,
                'message' => 'Cet objectif est déjà terminé.'
            ];
        }

        // Règle 3: Solde suffisant
        $userBalance = (float) str_replace(',', '.', (string) $user->getSolde());
        if ($userBalance < $amount) {
            return [
                'success' => false,
                'message' => 'Solde insuffisant.'
            ];
        }

        // Ajouter le montant
        $current = (float) str_replace(',', '.', (string) $goal->getCurrent_amount());
        $target = (float) str_replace(',', '.', (string) $goal->getTarget_amount());
        $newValue = $current + $amount;

        $goal->setCurrent_amount((string) $newValue);
        $user->setSolde((string) ($userBalance - $amount));

        // Vérifier si l'objectif est atteint
        $completed = false;
        if ($newValue >= $target) {
            $goal->setStatus('COMPLETED');
            $completed = true;
        }

        $this->entityManager->flush();

        return [
            'success' => true,
            'completed' => $completed
        ];
    }

    /**
     * Génère un ID unique pour un nouvel objectif.
     *
     * @return int L'ID généré
     */
    private function generateUniqueId(): int
    {
        $allIds = $this->entityManager->createQueryBuilder()
            ->select('g.id')
            ->from(Goal::class, 'g')
            ->orderBy('g.id', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
        
        $generatedId = 1;
        foreach ($allIds as $id) {
            if ($id == $generatedId) {
                $generatedId++;
            } elseif ($id > $generatedId) {
                break;
            }
        }

        return $generatedId;
    }

    /**
     * Récupère les objectifs d'un utilisateur.
     *
     * @param User $user L'utilisateur
     * @return Goal[] Les objectifs de l'utilisateur
     */
    public function getUserGoals(User $user): array
    {
        return $this->entityManager->getRepository(Goal::class)->findBy(
            ['id_user' => (int) $user->getId()],
            ['created_at' => 'DESC']
        );
    }

    /**
     * Calcule le montant restant pour atteindre l'objectif.
     *
     * @param Goal $goal L'objectif
     * @return float Le montant restant
     */
    public function getRemainingAmount(Goal $goal): float
    {
        $target = (float) str_replace(',', '.', (string) $goal->getTarget_amount());
        $current = (float) str_replace(',', '.', (string) $goal->getCurrent_amount());
        
        return max(0, $target - $current);
    }
}
