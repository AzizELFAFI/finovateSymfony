<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity]
class Goal
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "integer")]
    #[Assert\NotBlank(message: "L'utilisateur est obligatoire.")]
    private int $id_user;

    #[ORM\Column(type: "string", length: 40)]
    #[Assert\NotBlank(message: "Le titre est obligatoire.")]
    #[Assert\Type(type: "string", message: "Le titre doit être une chaîne.")]
    #[Assert\Length(min: 3, max: 40, minMessage: "Le titre doit contenir au moins {{ limit }} caractères.", maxMessage: "Le titre ne doit pas dépasser {{ limit }} caractères.")]
    private string $title;

    #[ORM\Column(type: "date")]
    #[Assert\NotBlank(message: "La deadline est obligatoire.")]
    #[Assert\Type(type: "\\DateTimeInterface", message: "Deadline invalide.")]
    private \DateTimeInterface $deadline;

    #[ORM\Column(type: "string", length: 20)]
    #[Assert\NotBlank(message: "Le statut est obligatoire.")]
    #[Assert\Length(max: 20)]
    private string $status;

    #[ORM\Column(type: "date")]
    #[Assert\NotBlank(message: "La date de création est obligatoire.")]
    #[Assert\Type(type: "\\DateTimeInterface", message: "Date de création invalide.")]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: "string", length: 255)]
    #[Assert\NotBlank(message: "Le montant objectif est obligatoire.")]
    #[Assert\Type(type: "numeric", message: "Le montant objectif doit être un nombre.")]
    #[Assert\Positive(message: "Le montant objectif doit être supérieur à 0.")]
    private string $target_amount;

    #[ORM\Column(type: "string", length: 255)]
    #[Assert\NotBlank(message: "Le montant actuel est obligatoire.")]
    #[Assert\Type(type: "numeric", message: "Le montant actuel doit être un nombre.")]
    #[Assert\GreaterThanOrEqual(value: 0, message: "Le montant actuel doit être supérieur ou égal à 0.")]
    private string $current_amount;

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

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($value)
    {
        $this->title = $value;
    }

    public function getDeadline()
    {
        return $this->deadline;
    }

    public function setDeadline($value)
    {
        $this->deadline = $value;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($value)
    {
        $this->status = $value;
    }

    public function getCreated_at()
    {
        return $this->created_at;
    }

    public function setCreated_at($value)
    {
        $this->created_at = $value;
    }

    public function getTarget_amount()
    {
        return $this->target_amount;
    }

    public function setTarget_amount($value)
    {
        $this->target_amount = $value;
    }

    public function getCurrent_amount()
    {
        return $this->current_amount;
    }

    public function setCurrent_amount($value)
    {
        $this->current_amount = $value;
    }
}
