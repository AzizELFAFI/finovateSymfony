<?php

namespace App\Entity;

use App\Repository\UserRestrictionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRestrictionRepository::class)]
#[ORM\Table(name: 'user_restrictions')]
class UserRestriction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'boolean')]
    private bool $canPost = false;

    #[ORM\Column(type: 'boolean')]
    private bool $canComment = false;

    #[ORM\Column(name: 'can_create_forum', type: 'boolean')]
    private bool $canCreateForum = false;

    #[ORM\Column(name: 'restricted_until', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $restrictedUntil = null;

    #[ORM\Column(type: 'text')]
    private string $reason;

    #[ORM\Column(name: 'offense_number', type: 'integer')]
    private int $offenseNumber;

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function isCanPost(): bool { return $this->canPost; }
    public function setCanPost(bool $canPost): static { $this->canPost = $canPost; return $this; }

    public function isCanComment(): bool { return $this->canComment; }
    public function setCanComment(bool $canComment): static { $this->canComment = $canComment; return $this; }

    public function isCanCreateForum(): bool { return $this->canCreateForum; }
    public function setCanCreateForum(bool $canCreateForum): static { $this->canCreateForum = $canCreateForum; return $this; }

    public function getRestrictedUntil(): ?\DateTimeImmutable { return $this->restrictedUntil; }
    public function setRestrictedUntil(?\DateTimeImmutable $restrictedUntil): static { $this->restrictedUntil = $restrictedUntil; return $this; }

    public function getReason(): string { return $this->reason; }
    public function setReason(string $reason): static { $this->reason = $reason; return $this; }

    public function getOffenseNumber(): int { return $this->offenseNumber; }
    public function setOffenseNumber(int $offenseNumber): static { $this->offenseNumber = $offenseNumber; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
}
