<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class PayBillRequest
{
    #[Assert\NotBlank(message: "La référence est obligatoire.")]
    #[Assert\Type(type: "string", message: "La référence doit être une chaîne.")]
    #[Assert\Length(min: 3, max: 50, minMessage: "La référence doit contenir au moins {{ limit }} caractères.", maxMessage: "La référence ne doit pas dépasser {{ limit }} caractères.")]
    private ?string $reference = null;

    #[Assert\NotBlank(message: "Le montant est obligatoire.")]
    #[Assert\Type(type: "numeric", message: "Le montant doit être un nombre.")]
    #[Assert\Positive(message: "Le montant doit être supérieur à 0.")]
    private ?string $amount = null;

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): void
    {
        $this->reference = $reference;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): void
    {
        $this->amount = $amount;
    }
}
