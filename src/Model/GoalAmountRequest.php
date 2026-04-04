<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class GoalAmountRequest
{
    #[Assert\NotBlank(message: "Le montant est obligatoire.")]
    #[Assert\Type(type: "numeric", message: "Le montant doit être un nombre.")]
    #[Assert\Positive(message: "Le montant doit être supérieur à 0.")]
    private ?string $amount = null;

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): void
    {
        $this->amount = $amount;
    }
}
