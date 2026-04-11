<?php

namespace App\Entity;

use App\Repository\SharedPostRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SharedPostRepository::class)]
#[ORM\Table(name: 'shared_posts')]
class SharedPost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'sharedPosts')]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Post $post = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'sharedPosts')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    private ?User $user = null;

    #[ORM\Column(name: 'shared_at', type: 'datetime')]
    private \DateTime $sharedAt;

    public function __construct()
    {
        $this->sharedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getPost(): ?Post { return $this->post; }
    public function setPost(?Post $post): static { $this->post = $post; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getSharedAt(): \DateTime { return $this->sharedAt; }
    public function setSharedAt(\DateTime $sharedAt): static { $this->sharedAt = $sharedAt; return $this; }

    // Kept for controller compatibility — no DB column
    public function getTargetForum(): ?Forum { return null; }
    public function setTargetForum(?Forum $targetForum): static { return $this; }
    public function getComment(): ?string { return null; }
    public function setComment(?string $comment): static { return $this; }
}
