<?php

namespace App\Entity;

use App\Repository\UserBlockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserBlockRepository::class)]
#[ORM\Table(name: 'user_blocks')]
#[ORM\UniqueConstraint(name: 'unique_block', columns: ['blocker_id', 'blocked_id'])]
class UserBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'blocker_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $blocker;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'blocked_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $blocked;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getBlocker(): User { return $this->blocker; }
    public function setBlocker(User $blocker): static { $this->blocker = $blocker; return $this; }
    public function getBlocked(): User { return $this->blocked; }
    public function setBlocked(User $blocked): static { $this->blocked = $blocked; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): static { $this->reason = $reason; return $this; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
}
