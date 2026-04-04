<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Forums;

#[ORM\Entity]
class User_badges
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

        #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "user_badgess")]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private User $user_id;

        #[ORM\ManyToOne(targetEntity: Badge_types::class, inversedBy: "user_badgess")]
    #[ORM\JoinColumn(name: 'badge_type_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Badge_types $badge_type_id;

        #[ORM\ManyToOne(targetEntity: Forums::class, inversedBy: "user_badgess")]
    #[ORM\JoinColumn(name: 'forum_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Forums $forum_id;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $earned_at;

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

    public function getBadge_type_id()
    {
        return $this->badge_type_id;
    }

    public function setBadge_type_id($value)
    {
        $this->badge_type_id = $value;
    }

    public function getForum_id()
    {
        return $this->forum_id;
    }

    public function setForum_id($value)
    {
        $this->forum_id = $value;
    }

    public function getEarned_at()
    {
        return $this->earned_at;
    }

    public function setEarned_at($value)
    {
        $this->earned_at = $value;
    }
}
