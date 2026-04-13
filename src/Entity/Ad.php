<?php

namespace App\Entity;

use App\Repository\AdRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AdRepository::class)]
class Ad
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre de l\'annonce est requis')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins 3 caractères',
        maxMessage: 'Le titre ne doit pas dépasser 255 caractères'
    )]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L\'image est requise')]
    private ?string $imagePath = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'La durée en secondes est requise')]
    #[Assert\Positive(message: 'La durée doit être positive')]
    #[Assert\Range(
        min: 1,
        max: 3600,
        notInRangeMessage: 'La durée doit être entre 1 et 3600 secondes'
    )]
    private ?int $duration = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Les points de récompense sont requis')]
    #[Assert\Positive(message: 'Les points doivent être positifs')]
    #[Assert\Range(
        min: 1,
        max: 10000,
        notInRangeMessage: 'Les points doivent être entre 1 et 10000'
    )]
    private ?int $rewardPoints = null;

    /**
     * @var Collection<int, UserAdClick>
     */
    #[ORM\OneToMany(targetEntity: UserAdClick::class, mappedBy: 'ad')]
    private Collection $userAdClicks;

    public function __construct()
    {
        $this->userAdClicks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getRewardPoints(): ?int
    {
        return $this->rewardPoints;
    }

    public function setRewardPoints(int $rewardPoints): static
    {
        $this->rewardPoints = $rewardPoints;

        return $this;
    }

    /**
     * @return Collection<int, UserAdClick>
     */
    public function getUserAdClicks(): Collection
    {
        return $this->userAdClicks;
    }

    public function addUserAdClick(UserAdClick $userAdClick): static
    {
        if (!$this->userAdClicks->contains($userAdClick)) {
            $this->userAdClicks->add($userAdClick);
            $userAdClick->setAd($this);
        }

        return $this;
    }

    public function removeUserAdClick(UserAdClick $userAdClick): static
    {
        if ($this->userAdClicks->removeElement($userAdClick)) {
            // set the owning side to null (unless already changed)
            if ($userAdClick->getAd() === $this) {
                $userAdClick->setAd(null);
            }
        }

        return $this;
    }
}
