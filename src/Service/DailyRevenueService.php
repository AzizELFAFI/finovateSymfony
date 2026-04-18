<?php

namespace App\Service;

use App\Entity\DailyRevenue;
use App\Entity\InvestorRevenueLog;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\DailyRevenueRepository;
use App\Repository\InvestissementRepository;
use App\Repository\ProjectRevenueShareRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DailyRevenueService
{
    public function __construct(
        private readonly DailyRevenueRepository $dailyRevenueRepository,
        private readonly ProjectRevenueShareRepository $projectRevenueShareRepository,
        private readonly InvestissementRepository $investissementRepository,
        private readonly InvestmentRevenueService $investmentRevenueService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Per-project UI state for owner « Gérer mes projets » (daily revenue block).
     *
     * @param iterable<Project> $projects
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildDailyRevenueUiStates(iterable $projects): array
    {
        $map = [];
        foreach ($projects as $project) {
            $id = $project->getId();
            if ($id === null) {
                continue;
            }
            $this->investmentRevenueService->ensureFundingCompletionRecorded($project);
            $this->em->refresh($project);
            $map[$id] = $this->computeDailyRevenueUiState($project);
        }

        return $map;
    }

    /**
     * Records revenue for the next required calendar day only (strict sequence from funding completion).
     */
    public function submitForProjectAndOwner(Project $project, User $owner, float $amount): DailyRevenue
    {
        if ($project->getOwner()?->getId() !== $owner->getId()) {
            throw new \RuntimeException('Accès refusé.');
        }

        $this->investmentRevenueService->ensureFundingCompletionRecorded($project);
        $this->em->refresh($project);

        $goal = round((float) str_replace(',', '.', (string) ($project->getGoalAmount() ?? '0')), 2);
        $current = round((float) str_replace(',', '.', (string) ($project->getCurrentAmount() ?? '0')), 2);

        if ($goal <= 0 || $current < $goal) {
            throw new \RuntimeException(
                sprintf(
                    'Les revenus quotidiens ne sont disponibles qu’une fois l’objectif atteint (collecté %s / objectif %s).',
                    number_format($current, 2, ',', ' '),
                    number_format($goal, 2, ',', ' ')
                )
            );
        }

        $completedAt = $project->getFundingCompletedAt();
        if ($completedAt === null) {
            throw new \RuntimeException('Date de clôture du financement non enregistrée. Réessayez dans un instant.');
        }

        if ($this->investissementRepository->countConfirmedByProject($project) === 0) {
            throw new \RuntimeException('Aucun investissement accepté pour ce projet.');
        }

        $anchor = $completedAt->setTime(0, 0, 0);
        $last = $this->dailyRevenueRepository->findLatestRevenueDate($project);
        $expected = $last === null ? $anchor : $last->modify('+1 day')->setTime(0, 0, 0);

        $today = (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
        $expectedStr = $expected->format('Y-m-d');
        $todayStr = $today->format('Y-m-d');

        if ($expectedStr > $todayStr) {
            throw new \RuntimeException(
                sprintf(
                    'La saisie des revenus démarre le jour où l’objectif est atteint, puis jour après jour. Prochaine date autorisée : %s.',
                    $expected->format('d/m/Y')
                )
            );
        }

        $day = $expected;

        $this->investmentRevenueService->syncRevenueShares($project);
        $this->em->flush();

        $shares = $this->projectRevenueShareRepository->findByProject($project);
        if ($shares === []) {
            throw new \RuntimeException('Aucune part de revenu à répartir — vérifiez les investissements acceptés.');
        }

        $existing = $this->dailyRevenueRepository->findOneByProjectAndDate($project, $day);
        if ($existing !== null) {
            throw new \RuntimeException('Un revenu existe déjà pour la date du '.$day->format('d/m/Y').'.');
        }

        $rev = new DailyRevenue();
        $rev->setProject($project);
        $rev->setRevenueDate($day);
        $rev->setAmount($amount);
        $this->em->persist($rev);
        $this->em->flush();

        $now = new \DateTimeImmutable();

        foreach ($shares as $share) {
            $pct = (float) str_replace(',', '.', $share->getPercentage());
            $earned = round($amount * ($pct / 100.0), 2);

            $log = new InvestorRevenueLog();
            $log->setProjectRevenueShare($share);
            $log->setDailyRevenue($rev);
            $user = $share->getUser();
            if ($user === null) {
                continue;
            }
            $log->setUser($user);
            $log->setAmountEarned(number_format($earned, 2, '.', ''));
            $log->setCreatedAt($now);
            $log->setUpdatedAt($now);
            $this->em->persist($log);
        }

        $this->em->flush();

        return $rev;
    }

    /**
     * @return array<string, mixed>
     */
    private function computeDailyRevenueUiState(Project $project): array
    {
        $goal = round((float) str_replace(',', '.', (string) ($project->getGoalAmount() ?? '0')), 2);
        $current = round((float) str_replace(',', '.', (string) ($project->getCurrentAmount() ?? '0')), 2);
        $funded = $goal > 0 && $current >= $goal;

        if (!$funded) {
            return [
                'funded' => false,
                'show_form' => false,
                'goal' => $goal,
                'current' => $current,
                'next_date' => null,
                'next_label' => null,
                'late' => false,
                'message' => sprintf(
                    'Les revenus quotidiens commencent uniquement après le financement complet de l’objectif (actuellement %s / %s).',
                    number_format($current, 2, ',', ' '),
                    number_format($goal, 2, ',', ' ')
                ),
            ];
        }

        $completedAt = $project->getFundingCompletedAt();
        if ($completedAt === null) {
            return [
                'funded' => true,
                'show_form' => false,
                'goal' => $goal,
                'current' => $current,
                'next_date' => null,
                'next_label' => null,
                'late' => false,
                'message' => 'Enregistrement de la date de financement en cours… Actualisez la page dans un instant.',
            ];
        }

        if ($this->investissementRepository->countConfirmedByProject($project) === 0) {
            return [
                'funded' => true,
                'show_form' => false,
                'goal' => $goal,
                'current' => $current,
                'next_date' => null,
                'next_label' => null,
                'late' => false,
                'message' => 'Aucun investissement accepté — impossible de répartir des revenus.',
            ];
        }

        $anchor = $completedAt->setTime(0, 0, 0);
        $last = $this->dailyRevenueRepository->findLatestRevenueDate($project);
        $expected = $last === null ? $anchor : $last->modify('+1 day')->setTime(0, 0, 0);

        $today = (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
        $expectedStr = $expected->format('Y-m-d');
        $todayStr = $today->format('Y-m-d');

        $showForm = ($expectedStr <= $todayStr);
        $late = ($expectedStr < $todayStr);

        $message = null;
        if ($showForm && $late) {
            $message = sprintf(
                'Saisissez d’abord le revenu du %s (les jours doivent être enchaînés sans saut).',
                $expected->format('d/m/Y')
            );
        } elseif (!$showForm) {
            $message = sprintf(
                'Prochaine saisie le %s. Calendrier : un jour après l’autre à partir du %s (financement complet).',
                $expected->format('d/m/Y'),
                $anchor->format('d/m/Y')
            );
        }

        return [
            'funded' => true,
            'show_form' => $showForm,
            'goal' => $goal,
            'current' => $current,
            'next_date' => $expectedStr,
            'next_label' => $expected->format('d/m/Y'),
            'late' => $late,
            'message' => $message,
        ];
    }
}
