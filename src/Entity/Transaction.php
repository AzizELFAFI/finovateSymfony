<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity]
class Transaction
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "bigint")]
    private ?int $id = null;

    #[ORM\Column(type: "integer")]
    #[Assert\NotBlank(message: "L'expéditeur est obligatoire.")]
    private int $sender_id;

    #[ORM\Column(type: "string", length: 255)]
    #[Assert\NotBlank(message: "Le montant est obligatoire.")]
    #[Assert\Type(type: "numeric", message: "Le montant doit être un nombre.")]
    #[Assert\Positive(message: "Le montant doit être supérieur à 0.")]
    private string $amount;

    #[ORM\Column(type: "string", length: 20)]
    #[Assert\NotBlank(message: "Le type est obligatoire.")]
    #[Assert\Length(max: 20)]
    private string $type;

    #[ORM\Column(type: "string", length: 100)]
    #[Assert\NotBlank(message: "La description est obligatoire.")]
    #[Assert\Type(type: "string", message: "La description doit être une chaîne.")]
    #[Assert\Length(max: 100)]
    private string $description;

    #[ORM\Column(type: "datetime")]
    #[Assert\NotBlank(message: "La date est obligatoire.")]
    #[Assert\Type(type: "\\DateTimeInterface", message: "Date invalide.")]
    private \DateTimeInterface $date;

    #[ORM\Column(type: "integer")]
    #[Assert\NotBlank(message: "Le bénéficiaire est obligatoire.")]
    private int $receiver_id;

    public function getId()
    {
        return $this->id;
    }

    public function getSender_id()
    {
        return $this->sender_id;
    }

    public function setSender_id($value)
    {
        $this->sender_id = $value;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function setAmount($value)
    {
        $this->amount = $value;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($value)
    {
        $this->type = $value;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($value)
    {
        $this->description = $value;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function setDate($value)
    {
        $this->date = $value;
    }

    public function getReceiver_id()
    {
        return $this->receiver_id;
    }

    public function setReceiver_id($value)
    {
        $this->receiver_id = $value;
    }
}
