<?php

namespace App\Entity;

use App\Repository\InvestorRevenueLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvestorRevenueLogRepository::class)]
#[ORM\Table(name: 'investor_revenue_log')]
#[ORM\UniqueConstraint(name: 'uniq_log_share_daily', columns: ['project_revenue_share_id', 'daily_revenue_id'])]
class InvestorRevenueLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: ProjectRevenueShare::class)]
    #[ORM\JoinColumn(name: 'project_revenue_share_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?ProjectRevenueShare $projectRevenueShare = null;

    #[ORM\ManyToOne(targetEntity: DailyRevenue::class)]
    #[ORM\JoinColumn(name: 'daily_revenue_id', referencedColumnName: 'revenue_id', nullable: false, onDelete: 'CASCADE')]
    private ?DailyRevenue $dailyRevenue = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $amount_earned = '0.00';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updated_at = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getProjectRevenueShare(): ?ProjectRevenueShare
    {
        return $this->projectRevenueShare;
    }

    public function setProjectRevenueShare(?ProjectRevenueShare $projectRevenueShare): static
    {
        $this->projectRevenueShare = $projectRevenueShare;

        return $this;
    }

    public function getDailyRevenue(): ?DailyRevenue
    {
        return $this->dailyRevenue;
    }

    public function setDailyRevenue(?DailyRevenue $dailyRevenue): static
    {
        $this->dailyRevenue = $dailyRevenue;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getAmountEarned(): string
    {
        return $this->amount_earned;
    }

    public function setAmountEarned(string $amount_earned): static
    {
        $this->amount_earned = $amount_earned;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }
}
