<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
class Transaction
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "integer")]
    private int $sender_id;

    #[ORM\Column(type: "string", length: 255)]
    private string $amount;

    #[ORM\Column(type: "string", length: 20)]
    private string $type;

    #[ORM\Column(type: "string", length: 100)]
    private string $description;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date;

    #[ORM\Column(type: "integer")]
    private int $receiver_id;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
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
