<?php

namespace App\Repository;

use App\Entity\InvestorRevenueLog;
use App\Entity\ProjectRevenueShare;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvestorRevenueLog>
 */
class InvestorRevenueLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvestorRevenueLog::class);
    }

    /**
     * @return InvestorRevenueLog[]
     */
    public function findForUserOrderedByRevenueDate(User $user): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.dailyRevenue', 'dr')->addSelect('dr')
            ->andWhere('l.user = :u')
            ->setParameter('u', $user)
            ->orderBy('dr.revenue_date', 'ASC')
            ->addOrderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array{day: string, cum: float}>
     */
    public function buildCumulativeSeriesForUser(User $user): array
    {
        return array_map(
            static fn (array $row): array => ['day' => $row['day'], 'cum' => $row['cum']],
            $this->buildDailyRowsWithCumulative($user)
        );
    }

    public function sumAmountEarnedForUser(User $user): string
    {
        $qb = $this->createQueryBuilder('l')
            ->select('SUM(l.amount_earned)')
            ->andWhere('l.user = :u')
            ->setParameter('u', $user);

        $sum = $qb->getQuery()->getSingleScalarResult();

        return $sum !== null ? (string) $sum : '0.00';
    }

    public function sumAmountEarnedForShare(ProjectRevenueShare $share): float
    {
        $sum = $this->createQueryBuilder('l')
            ->select('SUM(l.amount_earned)')
            ->andWhere('l.projectRevenueShare = :s')
            ->setParameter('s', $share)
            ->getQuery()
            ->getSingleScalarResult();

        return $sum !== null ? (float) str_replace(',', '.', (string) $sum) : 0.0;
    }

    /**
     * One row per calendar day: parts du jour + cumul (tous projets confondus).
     *
     * @return list<array{day: string, date_label: string, daily: float, cum: float}>
     */
    public function buildDailyRowsWithCumulative(User $user): array
    {
        $byDay = [];
        foreach ($this->findForUserOrderedByRevenueDate($user) as $log) {
            $dr = $log->getDailyRevenue();
            $d = $dr?->getRevenueDate()?->format('Y-m-d');
            if ($d === null) {
                continue;
            }
            $amt = (float) str_replace(',', '.', $log->getAmountEarned());
            if (!isset($byDay[$d])) {
                $byDay[$d] = 0.0;
            }
            $byDay[$d] += $amt;
        }
        ksort($byDay);

        $rows = [];
        $cum = 0.0;
        foreach ($byDay as $day => $daily) {
            $cum += $daily;
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $day);
            $rows[] = [
                'day' => $day,
                'date_label' => $parsed !== false ? $parsed->format('d/m/Y') : $day,
                'daily' => $daily,
                'cum' => $cum,
            ];
        }

        return $rows;
    }
}
