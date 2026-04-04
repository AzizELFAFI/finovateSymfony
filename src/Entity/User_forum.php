<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Forums;

#[ORM\Entity]
class User_forum
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

        #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "user_forums")]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private User $user_id;

        #[ORM\ManyToOne(targetEntity: Forums::class, inversedBy: "user_forums")]
    #[ORM\JoinColumn(name: 'forum_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Forums $forum_id;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $joined_at;

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

    public function getJoined_at()
    {
        return $this->joined_at;
    }

    public function setJoined_at($value)
    {
        $this->joined_at = $value;
    }
}
