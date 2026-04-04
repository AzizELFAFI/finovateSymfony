<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\Collection;
use App\Entity\User_badges;

#[ORM\Entity]
class Badge_types
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string", length: 100)]
    private string $name;

    #[ORM\Column(type: "text")]
    private string $description;

    #[ORM\Column(type: "string", length: 50)]
    private string $icon;

    #[ORM\Column(type: "string", length: 255)]
    private string $category;

    #[ORM\Column(type: "string", length: 255)]
    private string $requirement_type;

    #[ORM\Column(type: "integer")]
    private int $requirement_value;

    #[ORM\Column(type: "boolean")]
    private bool $forum_specific;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $created_at;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($value)
    {
        $this->name = $value;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($value)
    {
        $this->description = $value;
    }

    public function getIcon()
    {
        return $this->icon;
    }

    public function setIcon($value)
    {
        $this->icon = $value;
    }

    public function getCategory()
    {
        return $this->category;
    }

    public function setCategory($value)
    {
        $this->category = $value;
    }

    public function getRequirement_type()
    {
        return $this->requirement_type;
    }

    public function setRequirement_type($value)
    {
        $this->requirement_type = $value;
    }

    public function getRequirement_value()
    {
        return $this->requirement_value;
    }

    public function setRequirement_value($value)
    {
        $this->requirement_value = $value;
    }

    public function getForum_specific()
    {
        return $this->forum_specific;
    }

    public function setForum_specific($value)
    {
        $this->forum_specific = $value;
    }

    public function getCreated_at()
    {
        return $this->created_at;
    }

    public function setCreated_at($value)
    {
        $this->created_at = $value;
    }

    #[ORM\OneToMany(mappedBy: "badge_type_id", targetEntity: User_badges::class)]
    private Collection $user_badgess;

        public function getUser_badgess(): Collection
        {
            return $this->user_badgess;
        }
    
        public function addUser_badges(User_badges $user_badges): self
        {
            if (!$this->user_badgess->contains($user_badges)) {
                $this->user_badgess[] = $user_badges;
                $user_badges->setBadge_type_id($this);
            }
    
            return $this;
        }
    
        public function removeUser_badges(User_badges $user_badges): self
        {
            if ($this->user_badgess->removeElement($user_badges)) {
                // set the owning side to null (unless already changed)
                if ($user_badges->getBadge_type_id() === $this) {
                    $user_badges->setBadge_type_id(null);
                }
            }
    
            return $this;
        }
}
