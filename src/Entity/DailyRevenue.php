<?php

namespace App\Entity;

use App\Repository\DailyRevenueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DailyRevenueRepository::class)]
#[ORM\Table(name: 'daily_revenue')]
#[ORM\UniqueConstraint(name: 'uniq_daily_revenue_project_day', columns: ['project_id', 'revenue_date'])]
class DailyRevenue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'revenue_id')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $revenue_date = null;

    #[ORM\Column(type: Types::FLOAT)]
    private float $amount = 0.0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getRevenueDate(): ?\DateTimeImmutable
    {
        return $this->revenue_date;
    }

    public function setRevenueDate(\DateTimeImmutable $revenue_date): static
    {
        $this->revenue_date = $revenue_date;

        return $this;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }
}
