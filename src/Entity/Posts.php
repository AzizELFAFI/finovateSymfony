<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\User;
use Doctrine\Common\Collections\Collection;
use App\Entity\Votes;

#[ORM\Entity]
class Posts
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "integer")]
    private int $forum_id;

    #[ORM\Column(type: "string", length: 200)]
    private string $title;

    #[ORM\Column(type: "text")]
    private string $content;

        #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "postss")]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private User $author_id;

    #[ORM\Column(type: "string", length: 500)]
    private string $image_url;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $updated_at;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getForum_id()
    {
        return $this->forum_id;
    }

    public function setForum_id($value)
    {
        $this->forum_id = $value;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($value)
    {
        $this->title = $value;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function setContent($value)
    {
        $this->content = $value;
    }

    public function getAuthor_id()
    {
        return $this->author_id;
    }

    public function setAuthor_id($value)
    {
        $this->author_id = $value;
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

    public function getUpdated_at()
    {
        return $this->updated_at;
    }

    public function setUpdated_at($value)
    {
        $this->updated_at = $value;
    }

    #[ORM\OneToMany(mappedBy: "post_id", targetEntity: Comments::class)]
    private Collection $commentss;

        public function getCommentss(): Collection
        {
            return $this->commentss;
        }
    
        public function addComments(Comments $comments): self
        {
            if (!$this->commentss->contains($comments)) {
                $this->commentss[] = $comments;
                $comments->setPost_id($this);
            }
    
            return $this;
        }
    
        public function removeComments(Comments $comments): self
        {
            if ($this->commentss->removeElement($comments)) {
                // set the owning side to null (unless already changed)
                if ($comments->getPost_id() === $this) {
                    $comments->setPost_id(null);
                }
            }
    
            return $this;
        }

    #[ORM\OneToMany(mappedBy: "post_id", targetEntity: Shared_posts::class)]
    private Collection $shared_postss;

    #[ORM\OneToMany(mappedBy: "post_id", targetEntity: Votes::class)]
    private Collection $votess;
}
