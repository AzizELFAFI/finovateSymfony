<?php

namespace App\Entity;

use App\Repository\UserInteractionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserInteractionRepository::class)]
#[ORM\Table(name: 'user_interactions')]
#[ORM\UniqueConstraint(name: 'unique_interaction', columns: ['user_id', 'forum_id', 'interaction_type'])]
class UserInteraction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Forum::class)]
    #[ORM\JoinColumn(name: 'forum_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Forum $forum = null;

    // VIEW, CLICK, JOIN, POST, COMMENT, VOTE, SHARE
    #[ORM\Column(name: 'interaction_type', length: 20)]
    private string $interactionType;

    #[ORM\Column(name: 'interaction_count', type: 'integer', nullable: true)]
    private ?int $interactionCount = 1;

    #[ORM\Column(name: 'last_interaction', type: 'datetime')]
    private \DateTime $lastInteraction;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lastInteraction = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getForum(): ?Forum { return $this->forum; }
    public function setForum(?Forum $forum): static { $this->forum = $forum; return $this; }
    public function getInteractionType(): string { return $this->interactionType; }
    public function setInteractionType(string $interactionType): static { $this->interactionType = $interactionType; return $this; }
    public function getInteractionCount(): ?int { return $this->interactionCount; }
    public function setInteractionCount(?int $interactionCount): static { $this->interactionCount = $interactionCount; return $this; }
    public function getLastInteraction(): \DateTime { return $this->lastInteraction; }
    public function setLastInteraction(\DateTime $lastInteraction): static { $this->lastInteraction = $lastInteraction; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
}
