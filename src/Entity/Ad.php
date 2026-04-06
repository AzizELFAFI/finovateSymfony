<?php

namespace App\Entity;

use App\Repository\AdRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdRepository::class)]
class Ad
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $imagePath = null;

    #[ORM\Column]
    private ?int $duration = null;

    #[ORM\Column]
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
