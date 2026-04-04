<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class TransferRequest
{
    #[Assert\NotBlank(message: "Le CIN est obligatoire.")]
    #[Assert\Regex(pattern: "/^\d{8}$/", message: "Le CIN doit contenir exactement 8 chiffres.")]
    private ?string $cin = null;

    #[Assert\NotBlank(message: "Le montant est obligatoire.")]
    #[Assert\Type(type: "numeric", message: "Le montant doit être un nombre.")]
    #[Assert\Positive(message: "Le montant doit être supérieur à 0.")]
    private ?string $montant = null;

    #[Assert\NotBlank(message: "La description est obligatoire.")]
    #[Assert\Type(type: "string", message: "La description doit être une chaîne.")]
    #[Assert\Length(max: 100, maxMessage: "La description ne doit pas dépasser {{ limit }} caractères.")]
    private ?string $description = null;

    public function getCin(): ?string
    {
        return $this->cin;
    }

    public function setCin(?string $cin): void
    {
        $this->cin = $cin;
    }

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(?string $montant): void
    {
        $this->montant = $montant;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }
}
