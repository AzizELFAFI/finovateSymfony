<?php

namespace App\Entity;

use App\Repository\ForumRecommendationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumRecommendationRepository::class)]
#[ORM\Table(name: 'forum_recommendations')]
#[ORM\UniqueConstraint(name: 'unique_recommendation', columns: ['user_id', 'forum_id'])]
class ForumRecommendation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'forumRecommendations')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Forum::class)]
    #[ORM\JoinColumn(name: 'forum_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Forum $forum = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $score = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getForum(): ?Forum { return $this->forum; }
    public function setForum(?Forum $forum): static { $this->forum = $forum; return $this; }
    public function getScore(): ?float { return $this->score; }
    public function setScore(?float $score): static { $this->score = $score; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): static { $this->reason = $reason; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
}
