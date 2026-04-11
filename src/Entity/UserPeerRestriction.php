<?php

namespace App\Entity;

use App\Repository\UserPeerRestrictionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A restriction applied by one user to another (not admin).
 * The restricted user can still see content but cannot interact
 * (share, join forums, comment, vote) with the restrictor's content.
 */
#[ORM\Entity(repositoryClass: UserPeerRestrictionRepository::class)]
#[ORM\Table(name: 'user_peer_restrictions')]
class UserPeerRestriction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'restrictor_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $restrictor;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'restricted_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $restricted;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    /** null = until manual lift */
    #[ORM\Column(name: 'restricted_until', type: 'datetime', nullable: true)]
    private ?\DateTime $restrictedUntil = null;

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getRestrictor(): User { return $this->restrictor; }
    public function setRestrictor(User $restrictor): static { $this->restrictor = $restrictor; return $this; }
    public function getRestricted(): User { return $this->restricted; }
    public function setRestricted(User $restricted): static { $this->restricted = $restricted; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): static { $this->reason = $reason; return $this; }
    public function getRestrictedUntil(): ?\DateTime { return $this->restrictedUntil; }
    public function setRestrictedUntil(?\DateTime $restrictedUntil): static { $this->restrictedUntil = $restrictedUntil; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }

    public function isExpired(): bool
    {
        return $this->restrictedUntil !== null && $this->restrictedUntil < new \DateTime();
    }
}
