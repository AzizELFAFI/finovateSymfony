<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity]
class Bill
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "integer")]
    #[Assert\NotBlank(message: "L'utilisateur est obligatoire.")]
    private int $id_user;

    #[ORM\Column(type: "string", length: 50)]
    #[Assert\NotBlank(message: "La référence est obligatoire.")]
    #[Assert\Type(type: "string", message: "La référence doit être une chaîne.")]
    #[Assert\Length(min: 3, max: 50, minMessage: "La référence doit contenir au moins {{ limit }} caractères.", maxMessage: "La référence ne doit pas dépasser {{ limit }} caractères.")]
    private string $reference;

    #[ORM\Column(type: "float")]
    #[Assert\NotBlank(message: "Le montant est obligatoire.")]
    #[Assert\Type(type: "numeric", message: "Le montant doit être un nombre.")]
    #[Assert\Positive(message: "Le montant doit être supérieur à 0.")]
    private float $amount;

    #[ORM\Column(type: "date")]
    #[Assert\NotBlank(message: "La date de paiement est obligatoire.")]
    #[Assert\Type(type: "\\DateTimeInterface", message: "Date de paiement invalide.")]
    private \DateTimeInterface $date_paiement;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getId_user()
    {
        return $this->id_user;
    }

    public function setId_user($value)
    {
        $this->id_user = $value;
    }

    public function getReference()
    {
        return $this->reference;
    }

    public function setReference($value)
    {
        $this->reference = $value;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function setAmount($value)
    {
        $this->amount = $value;
    }

    public function getDate_paiement()
    {
        return $this->date_paiement;
    }

    public function setDate_paiement($value)
    {
        $this->date_paiement = $value;
    }
}
