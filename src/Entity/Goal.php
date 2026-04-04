<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
class Goal
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "integer")]
    private int $id_user;

    #[ORM\Column(type: "string", length: 40)]
    private string $title;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $deadline;

    #[ORM\Column(type: "string", length: 20)]
    private string $status;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: "string", length: 255)]
    private string $target_amount;

    #[ORM\Column(type: "string", length: 255)]
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
