<?php

namespace App\Entity;

use App\Repository\UserForumRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserForumRepository::class)]
#[ORM\Table(name: 'user_forum')]
#[ORM\UniqueConstraint(name: 'unique_membership', columns: ['user_id', 'forum_id'])]
class UserForum
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userForums')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Forum::class, inversedBy: 'members')]
    #[ORM\JoinColumn(name: 'forum_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Forum $forum = null;

    #[ORM\Column(name: 'joined_at', type: 'datetime')]
    private \DateTime $joinedAt;

    public function __construct()
    {
        $this->joinedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getForum(): ?Forum { return $this->forum; }
    public function setForum(?Forum $forum): static { $this->forum = $forum; return $this; }
    public function getJoinedAt(): \DateTime { return $this->joinedAt; }
    public function setJoinedAt(\DateTime $joinedAt): static { $this->joinedAt = $joinedAt; return $this; }
}
