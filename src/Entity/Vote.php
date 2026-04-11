<?php

namespace App\Entity;

use App\Repository\VoteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VoteRepository::class)]
#[ORM\Table(name: 'votes')]
#[ORM\UniqueConstraint(name: 'unique_vote', columns: ['post_id', 'user_id'])]
class Vote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'votes')]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Post $post = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'votes')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    private ?User $user = null;

    #[ORM\Column(name: 'vote_type', type: 'string', length: 10)]
    private string $voteType; // UPVOTE or DOWNVOTE

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getPost(): ?Post { return $this->post; }
    public function setPost(?Post $post): static { $this->post = $post; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getVoteType(): string { return $this->voteType; }
    public function setVoteType(string $voteType): static { $this->voteType = $voteType; return $this; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt): static { $this->createdAt = $createdAt; return $this; }
}
