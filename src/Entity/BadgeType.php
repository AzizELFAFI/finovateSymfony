<?php

namespace App\Entity;

use App\Repository\BadgeTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BadgeTypeRepository::class)]
#[ORM\Table(name: 'badge_types')]
class BadgeType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(name: 'requirement_type', length: 20)]
    private string $requirementType;

    #[ORM\Column(name: 'requirement_value', type: 'integer')]
    private int $requirementValue;

    #[ORM\Column(name: 'forum_specific', type: 'boolean', nullable: true)]
    private ?bool $forumSpecific = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'badgeType', targetEntity: UserBadge::class)]
    private Collection $userBadges;

    public function __construct()
    {
        $this->userBadges = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getIcon(): ?string { return $this->icon; }
    public function setIcon(?string $icon): static { $this->icon = $icon; return $this; }
    public function getCategory(): ?string { return $this->category; }
    public function setCategory(?string $category): static { $this->category = $category; return $this; }
    public function getRequirementType(): string { return $this->requirementType; }
    public function setRequirementType(string $requirementType): static { $this->requirementType = $requirementType; return $this; }
    public function getRequirementValue(): int { return $this->requirementValue; }
    public function setRequirementValue(int $requirementValue): static { $this->requirementValue = $requirementValue; return $this; }
    public function isForumSpecific(): ?bool { return $this->forumSpecific; }
    public function setForumSpecific(?bool $forumSpecific): static { $this->forumSpecific = $forumSpecific; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUserBadges(): Collection { return $this->userBadges; }
}
