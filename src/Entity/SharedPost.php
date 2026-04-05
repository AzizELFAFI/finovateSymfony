<?php

namespace App\Entity;

use App\Repository\SharedPostRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Forum;

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

    #[ORM\Column(name: 'shared_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $sharedAt;

    #[ORM\ManyToOne(targetEntity: Forum::class)]
    #[ORM\JoinColumn(name: 'target_forum_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Forum $targetForum = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    public function __construct()
    {
        $this->sharedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getPost(): ?Post { return $this->post; }
    public function setPost(?Post $post): static { $this->post = $post; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getSharedAt(): \DateTimeImmutable { return $this->sharedAt; }
    public function setSharedAt(\DateTimeImmutable $sharedAt): static { $this->sharedAt = $sharedAt; return $this; }
    public function getTargetForum(): ?Forum { return $this->targetForum; }
    public function setTargetForum(?Forum $targetForum): static { $this->targetForum = $targetForum; return $this; }
    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): static { $this->comment = $comment; return $this; }
}
