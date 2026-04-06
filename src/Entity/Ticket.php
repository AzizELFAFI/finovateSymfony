<?php

namespace App\Entity;

use App\Repository\TicketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\Table(name: 'ticket')]
class Ticket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'type', length: 100, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'dateCreation', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(name: 'priorite', length: 50, nullable: true)]
    private ?string $priorite = null;

    #[ORM\Column(name: 'statut', length: 50, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(name: 'user_id', type: Types::BIGINT, nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(name: 'date_resolution', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateResolution = null;

    #[ORM\OneToMany(mappedBy: 'ticket', targetEntity: Message::class)]
    private Collection $messages;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getType(): ?string { return $this->type; }
    public function setType(?string $type): static { $this->type = $type; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(?\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }

    public function getPriorite(): ?string { return $this->priorite; }
    public function setPriorite(?string $priorite): static { $this->priorite = $priorite; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(?string $statut): static { $this->statut = $statut; return $this; }

    public function getUserId(): ?int { return $this->userId; }
    public function setUserId(?int $userId): static { $this->userId = $userId; return $this; }

    public function getDateResolution(): ?\DateTimeInterface { return $this->dateResolution; }
    public function setDateResolution(?\DateTimeInterface $dateResolution): static { $this->dateResolution = $dateResolution; return $this; }

    public function getMessages(): Collection { return $this->messages; }

    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setTicket($this);
        }
        return $this;
    }

    public function removeMessage(Message $message): static
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getTicket() === $this) {
                $message->setTicket(null);
            }
        }
        return $this;
    }
}