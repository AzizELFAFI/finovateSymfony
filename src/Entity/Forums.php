<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\User;
use Doctrine\Common\Collections\Collection;
use App\Entity\User_badges;

#[ORM\Entity]
class Forums
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string", length: 200)]
    private string $title;

    #[ORM\Column(type: "text")]
    private string $description;

        #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "forumss")]
    #[ORM\JoinColumn(name: 'creator_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private User $creator_id;

    #[ORM\Column(type: "string", length: 500)]
    private string $image_url;

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

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($value)
    {
        $this->title = $value;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($value)
    {
        $this->description = $value;
    }

    public function getCreator_id()
    {
        return $this->creator_id;
    }

    public function setCreator_id($value)
    {
        $this->creator_id = $value;
    }

    public function getImage_url()
    {
        return $this->image_url;
    }

    public function setImage_url($value)
    {
        $this->image_url = $value;
    }

    public function getCreated_at()
    {
        return $this->created_at;
    }

    public function setCreated_at($value)
    {
        $this->created_at = $value;
    }

    #[ORM\OneToMany(mappedBy: "forum_id", targetEntity: Forum_recommendations::class)]
    private Collection $forum_recommendationss;

        public function getForum_recommendationss(): Collection
        {
            return $this->forum_recommendationss;
        }
    
        public function addForum_recommendations(Forum_recommendations $forum_recommendations): self
        {
            if (!$this->forum_recommendationss->contains($forum_recommendations)) {
                $this->forum_recommendationss[] = $forum_recommendations;
                $forum_recommendations->setForum_id($this);
            }
    
            return $this;
        }
    
        public function removeForum_recommendations(Forum_recommendations $forum_recommendations): self
        {
            if ($this->forum_recommendationss->removeElement($forum_recommendations)) {
                // set the owning side to null (unless already changed)
                if ($forum_recommendations->getForum_id() === $this) {
                    $forum_recommendations->setForum_id(null);
                }
            }
    
            return $this;
        }

    #[ORM\OneToMany(mappedBy: "forum_id", targetEntity: User_forum::class)]
    private Collection $user_forums;

    #[ORM\OneToMany(mappedBy: "forum_id", targetEntity: User_interactions::class)]
    private Collection $user_interactionss;

    #[ORM\OneToMany(mappedBy: "forum_id", targetEntity: User_badges::class)]
    private Collection $user_badgess;
}
