<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class CreateGoalRequest
{
    #[Assert\NotBlank(message: "Le titre est obligatoire.")]
    #[Assert\Type(type: "string", message: "Le titre doit être une chaîne.")]
    #[Assert\Length(min: 3, max: 40, minMessage: "Le titre doit contenir au moins {{ limit }} caractères.", maxMessage: "Le titre ne doit pas dépasser {{ limit }} caractères.")]
    private ?string $title = null;

    #[Assert\NotBlank(message: "La deadline est obligatoire.")]
    #[Assert\Type(type: "\\DateTimeInterface", message: "Deadline invalide.")]
    #[Assert\GreaterThan(value: "today", message: "La deadline doit être dans le futur.")]
    private ?\DateTimeInterface $deadline = null;

    #[Assert\NotBlank(message: "Le montant objectif est obligatoire.")]
    #[Assert\Type(type: "numeric", message: "Le montant objectif doit être un nombre.")]
    #[Assert\Positive(message: "Le montant objectif doit être supérieur à 0.")]
    private ?string $targetAmount = null;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getDeadline(): ?\DateTimeInterface
    {
        return $this->deadline;
    }

    public function setDeadline(?\DateTimeInterface $deadline): void
    {
        $this->deadline = $deadline;
    }

    public function getTargetAmount(): ?string
    {
        return $this->targetAmount;
    }

    public function setTargetAmount(?string $targetAmount): void
    {
        $this->targetAmount = $targetAmount;
    }
}
