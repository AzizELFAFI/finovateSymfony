<?php

namespace App\Entity;

use App\Repository\FlaggedContentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FlaggedContentRepository::class)]
#[ORM\Table(name: 'flagged_content')]
class FlaggedContent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $contentType;

    #[ORM\Column(type: 'integer')]
    private int $contentId;

    #[ORM\Column(length: 20)]
    private string $severity;

    #[ORM\Column(length: 50)]
    private string $flagType;

    #[ORM\Column(type: 'text')]
    private string $verdict;

    #[ORM\Column(type: 'json')]
    private array $issues = [];

    #[ORM\Column(type: 'boolean')]
    private bool $reviewed = false;

    #[ORM\Column(type: 'boolean')]
    private bool $ignored = false;

    #[ORM\Column(name: 'detected_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $detectedAt;

    public function __construct()
    {
        $this->detectedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getContentType(): string { return $this->contentType; }
    public function setContentType(string $contentType): static { $this->contentType = $contentType; return $this; }

    public function getContentId(): int { return $this->contentId; }
    public function setContentId(int $contentId): static { $this->contentId = $contentId; return $this; }

    public function getSeverity(): string { return $this->severity; }
    public function setSeverity(string $severity): static { $this->severity = $severity; return $this; }

    public function getFlagType(): string { return $this->flagType; }
    public function setFlagType(string $flagType): static { $this->flagType = $flagType; return $this; }

    public function getVerdict(): string { return $this->verdict; }
    public function setVerdict(string $verdict): static { $this->verdict = $verdict; return $this; }

    public function getIssues(): array { return $this->issues; }
    public function setIssues(array $issues): static { $this->issues = $issues; return $this; }

    public function isReviewed(): bool { return $this->reviewed; }
    public function setReviewed(bool $reviewed): static { $this->reviewed = $reviewed; return $this; }

    public function isIgnored(): bool { return $this->ignored; }
    public function setIgnored(bool $ignored): static { $this->ignored = $ignored; return $this; }

    public function getDetectedAt(): \DateTimeImmutable { return $this->detectedAt; }
    public function setDetectedAt(\DateTimeImmutable $detectedAt): static { $this->detectedAt = $detectedAt; return $this; }
}
