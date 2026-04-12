<?php

namespace App\Entity;

use App\Repository\UserReportRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserReportRepository::class)]
#[ORM\Table(name: 'user_reports')]
class UserReport
{
    const REASON_SPAM        = 'Spam ou contenu répétitif';
    const REASON_MISLEADING  = 'Informations trompeuses ou fausses';
    const REASON_OFFENSIVE   = 'Contenu offensant ou haineux';
    const REASON_SCAM        = 'Arnaque ou fraude financière';
    const REASON_OTHER       = 'Autre';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Post::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Post $post;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reporter = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $reason;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $details = null;

    #[ORM\Column(type: 'boolean')]
    private bool $treated = false;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getPost(): Post { return $this->post; }
    public function setPost(Post $post): static { $this->post = $post; return $this; }
    public function getReporter(): ?User { return $this->reporter; }
    public function setReporter(?User $reporter): static { $this->reporter = $reporter; return $this; }
    public function getReason(): string { return $this->reason; }
    public function setReason(string $reason): static { $this->reason = $reason; return $this; }
    public function getDetails(): ?string { return $this->details; }
    public function setDetails(?string $details): static { $this->details = $details; return $this; }
    public function isTreated(): bool { return $this->treated; }
    public function setTreated(bool $treated): static { $this->treated = $treated; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
