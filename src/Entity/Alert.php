<?php

namespace App\Entity;

use App\Repository\AlertRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlertRepository::class)]
#[ORM\Table(name: 'alerts')]
class Alert
{
    // Alert types
    const TYPE_JOIN        = 'join';
    const TYPE_LEAVE       = 'leave';
    const TYPE_NEW_POST    = 'new_post';
    const TYPE_VOTE        = 'vote';
    const TYPE_COMMENT     = 'comment';
    const TYPE_SHARE       = 'share';
    const TYPE_BADGE       = 'badge';
    const TYPE_MODERATION  = 'moderation';
    const TYPE_WARNING     = 'warning';
    const TYPE_RESTRICTION = 'restriction';
    const TYPE_BAN         = 'ban';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private string $type;

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\Column(name: 'is_read', type: 'boolean')]
    private bool $isRead = false;

    #[ORM\Column(name: 'related_url', length: 500, nullable: true)]
    private ?string $relatedUrl = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }
    public function isRead(): bool { return $this->isRead; }
    public function setIsRead(bool $isRead): static { $this->isRead = $isRead; return $this; }
    public function getRelatedUrl(): ?string { return $this->relatedUrl; }
    public function setRelatedUrl(?string $relatedUrl): static { $this->relatedUrl = $relatedUrl; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getTypeIcon(): string
    {
        return match($this->type) {
            self::TYPE_JOIN     => '👥',
            self::TYPE_LEAVE    => '👋',
            self::TYPE_NEW_POST => '📝',
            self::TYPE_VOTE     => '👍',
            self::TYPE_COMMENT  => '💬',
            self::TYPE_SHARE    => '📤',
            self::TYPE_BADGE    => '🏆',
            default             => '🔔',
        };
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            self::TYPE_JOIN     => 'Rejoindre',
            self::TYPE_LEAVE    => 'Quitter',
            self::TYPE_NEW_POST => 'Nouveau post',
            self::TYPE_VOTE     => 'Vote',
            self::TYPE_COMMENT  => 'Commentaire',
            self::TYPE_SHARE    => 'Partage',
            self::TYPE_BADGE    => 'Badge',
            default             => 'Notification',
        };
    }
}
