<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'message')]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'Id', type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Ticket::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'idTicket', referencedColumnName: 'id', nullable: false)]
    private ?Ticket $ticket = null;

    #[ORM\Column(type: 'text')]
    private ?string $content = null;

    #[ORM\Column(name: 'sentAt', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $sentAt = null;

    #[ORM\Column(name: 'senderRole', type: 'string', length: 10)]
    private ?string $senderRole = 'USER';

    public function __construct()
    {
        $this->sentAt = new \DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTicket(): ?Ticket
    {
        return $this->ticket;
    }

    public function setTicket(?Ticket $ticket): self
    {
        $this->ticket = $ticket;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getSentAt(): ?\DateTimeInterface
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeInterface $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getSenderRole(): ?string
    {
        return $this->senderRole;
    }

    public function setSenderRole(string $senderRole): self
    {
        $this->senderRole = $senderRole;
        return $this;
    }
}
