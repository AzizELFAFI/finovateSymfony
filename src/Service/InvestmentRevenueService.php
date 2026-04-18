<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\ProjectAmountHistory;
use App\Entity\ProjectRevenueShare;
use App\Repository\InvestissementRepository;
use App\Repository\ProjectRevenueShareRepository;
use Doctrine\ORM\EntityManagerInterface;

final class InvestmentRevenueService
{
    public function __construct(
        private readonly InvestissementRepository $investissementRepository,
        private readonly ProjectRevenueShareRepository $projectRevenueShareRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function onInvestmentAccepted(Project $project): void
    {
        $this->em->refresh($project);
        $this->recordFundingSnapshot($project);
        $this->syncRevenueShares($project);
        $this->em->flush();
        $this->ensureFundingCompletionRecorded($project);
    }

    /**
     * Sets funding_completed_at once collected amount reaches goal (starts daily revenue calendar).
     */
    public function ensureFundingCompletionRecorded(Project $project): void
    {
        $this->em->refresh($project);

        $goal = round((float) str_replace(',', '.', (string) ($project->getGoalAmount() ?? '0')), 2);
        $current = round((float) str_replace(',', '.', (string) ($project->getCurrentAmount() ?? '0')), 2);

        if ($goal <= 0 || $current < $goal) {
            return;
        }

        if ($project->getFundingCompletedAt() !== null) {
            return;
        }

        $project->setFundingCompletedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    private function recordFundingSnapshot(Project $project): void
    {
        $current = (float) str_replace(',', '.', (string) ($project->getCurrentAmount() ?? '0'));
        $row = new ProjectAmountHistory();
        $row->setProject($project);
        $row->setAmount($current);
        $row->setRecordedAt(new \DateTimeImmutable());
        $this->em->persist($row);
    }

    /**
     * Stores each investor's share of daily revenue: the % they requested when submitting the demande
     * (revenue_percentage). If they left it blank, falls back to proportional split by amount invested.
     */
    public function syncRevenueShares(Project $project): void
    {
        $investments = $this->investissementRepository->findConfirmedByProject($project);

        /** @var array<int, \App\Entity\Investissement> $confirmedById */
        $confirmedById = [];
        foreach ($investments as $inv) {
            $id = $inv->getId();
            if ($id !== null) {
                $confirmedById[$id] = $inv;
            }
        }

        foreach ($this->projectRevenueShareRepository->findByProject($project) as $share) {
            $inv = $share->getInvestissement();
            $invId = $inv?->getId();
            if ($invId === null || !isset($confirmedById[$invId])) {
                $this->em->remove($share);
            }
        }

        $total = 0.0;
        foreach ($investments as $inv) {
            $total += (float) str_replace(',', '.', (string) $inv->getAmount());
        }

        if ($total <= 0.0) {
            return;
        }

        $now = new \DateTimeImmutable();

        foreach ($investments as $inv) {
            $amt = (float) str_replace(',', '.', (string) $inv->getAmount());
            $requested = $inv->getRevenuePercentage();

            if ($requested !== null) {
                $r = max(0.0, min(100.0, $requested));
                $pctStr = number_format($r, 4, '.', '');
            } else {
                $pct = ($amt / $total) * 100.0;
                $pctStr = number_format($pct, 4, '.', '');
            }

            $share = $this->projectRevenueShareRepository->findOneByInvestissement($inv);
            $investor = $inv->getUser();
            if ($investor === null) {
                continue;
            }

            if ($share === null) {
                $share = new ProjectRevenueShare();
                $share->setProject($project);
                $share->setInvestissement($inv);
                $share->setUser($investor);
                $share->setCreatedAt($now);
                $this->em->persist($share);
            }

            $share->setPercentage($pctStr);
            $share->setUpdatedAt($now);
        }
    }
}
