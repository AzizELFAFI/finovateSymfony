<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Forums;

#[ORM\Entity]
class Forum_recommendations
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

        #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "forum_recommendationss")]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private User $user_id;

        #[ORM\ManyToOne(targetEntity: Forums::class, inversedBy: "forum_recommendationss")]
    #[ORM\JoinColumn(name: 'forum_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Forums $forum_id;

    #[ORM\Column(type: "float")]
    private float $score;

    #[ORM\Column(type: "text")]
    private string $reason;

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

    public function getScore()
    {
        return $this->score;
    }

    public function setScore($value)
    {
        $this->score = $value;
    }

    public function getReason()
    {
        return $this->reason;
    }

    public function setReason($value)
    {
        $this->reason = $value;
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
