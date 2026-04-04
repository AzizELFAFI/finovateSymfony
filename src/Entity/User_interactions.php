<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Forums;

#[ORM\Entity]
class User_interactions
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

        #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "user_interactionss")]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private User $user_id;

        #[ORM\ManyToOne(targetEntity: Forums::class, inversedBy: "user_interactionss")]
    #[ORM\JoinColumn(name: 'forum_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Forums $forum_id;

    #[ORM\Column(type: "string", length: 255)]
    private string $interaction_type;

    #[ORM\Column(type: "integer")]
    private int $interaction_count;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $last_interaction;

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

    public function getUser_id()
    {
        return $this->user_id;
    }

    public function setUser_id($value)
    {
        $this->user_id = $value;
    }

    public function getForum_id()
    {
        return $this->forum_id;
    }

    public function setForum_id($value)
    {
        $this->forum_id = $value;
    }

    public function getInteraction_type()
    {
        return $this->interaction_type;
    }

    public function setInteraction_type($value)
    {
        $this->interaction_type = $value;
    }

    public function getInteraction_count()
    {
        return $this->interaction_count;
    }

    public function setInteraction_count($value)
    {
        $this->interaction_count = $value;
    }

    public function getLast_interaction()
    {
        return $this->last_interaction;
    }

    public function setLast_interaction($value)
    {
        $this->last_interaction = $value;
    }

    public function getCreated_at()
    {
        return $this->created_at;
    }

    public function setCreated_at($value)
    {
        $this->created_at = $value;
    }
}
