<?php

namespace App\Entity;

use App\Repository\UserBadgeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserBadgeRepository::class)]
#[ORM\Table(name: 'user_badges')]
class UserBadge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userBadges')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: BadgeType::class, inversedBy: 'userBadges')]
    #[ORM\JoinColumn(name: 'badge_type_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?BadgeType $badgeType = null;

    #[ORM\ManyToOne(targetEntity: Forum::class)]
    #[ORM\JoinColumn(name: 'forum_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Forum $forum = null;

    #[ORM\Column(name: 'earned_at', type: 'datetime')]
    private \DateTime $earnedAt;

    public function __construct()
    {
        $this->earnedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getBadgeType(): ?BadgeType { return $this->badgeType; }
    public function setBadgeType(?BadgeType $badgeType): static { $this->badgeType = $badgeType; return $this; }
    public function getForum(): ?Forum { return $this->forum; }
    public function setForum(?Forum $forum): static { $this->forum = $forum; return $this; }
    public function getEarnedAt(): \DateTime { return $this->earnedAt; }
    public function setEarnedAt(\DateTime $earnedAt): static { $this->earnedAt = $earnedAt; return $this; }
}
