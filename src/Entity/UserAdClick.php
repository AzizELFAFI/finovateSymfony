<?php

namespace App\Entity;

use App\Repository\UserAdClickRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserAdClickRepository::class)]
class UserAdClick
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userAdClicks')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'userAdClicks')]
    private ?Ad $ad = null;

    #[ORM\Column]
    private ?\DateTime $clickedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getAd(): ?Ad
    {
        return $this->ad;
    }

    public function setAd(?Ad $ad): static
    {
        $this->ad = $ad;

        return $this;
    }

    public function getClickedAt(): ?\DateTime
    {
        return $this->clickedAt;
    }

    public function setClickedAt(\DateTime $clickedAt): static
    {
        $this->clickedAt = $clickedAt;

        return $this;
    }
}
