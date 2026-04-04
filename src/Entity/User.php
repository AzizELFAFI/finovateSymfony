<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\Collection;
use App\Entity\User_badges;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{

    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    private string $id;

    #[ORM\Column(type: "string", length: 255)]
    private string $email;

    #[ORM\Column(type: "string", length: 255)]
    private string $password;

    #[ORM\Column(type: "string", length: 100)]
    private string $firstname;

    #[ORM\Column(type: "string", length: 100)]
    private string $lastname;

    #[ORM\Column(type: "string", length: 50)]
    private string $role;

    #[ORM\Column(type: "integer")]
    private int $points;

    #[ORM\Column(type: "string", length: 255)]
    private string $solde;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $birthdate;

    #[ORM\Column(type: "string", length: 50)]
    private string $cin;

    #[ORM\Column(type: "integer")]
    private int $phone_number;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: "bigint")]
    private string $numero_carte;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $value): void
    {
        $this->id = $value;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $value): void
    {
        $this->email = $value;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $value): void
    {
        $this->password = $value;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $role = $this->role ?: 'USER';
        // Symfony security attend des rôles commençant par ROLE_
        if ($role === 'USER') {
            return ['ROLE_USER'];
        }
        if ($role === 'ADMIN') {
            return ['ROLE_ADMIN'];
        }
        return [$role];
    }

    public function eraseCredentials(): void
    {
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function setFirstname(string $value): void
    {
        $this->firstname = $value;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function setLastname(string $value): void
    {
        $this->lastname = $value;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $value): void
    {
        $this->role = $value;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $value): void
    {
        $this->points = $value;
    }

    public function getSolde(): string
    {
        return $this->solde;
    }

    public function setSolde(string $value): void
    {
        $this->solde = $value;
    }

    public function getBirthdate(): \DateTimeInterface
    {
        return $this->birthdate;
    }

    public function setBirthdate(\DateTimeInterface $value): void
    {
        $this->birthdate = $value;
    }

    public function getCin(): string
    {
        return $this->cin;
    }

    public function setCin(string $value): void
    {
        $this->cin = $value;
    }

    public function getPhone_number(): int
    {
        return $this->phone_number;
    }

    public function setPhone_number(int $value): void
    {
        $this->phone_number = $value;
    }

    public function getCreated_at(): \DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(\DateTimeInterface $value): void
    {
        $this->created_at = $value;
    }

    public function getNumero_carte(): string
    {
        return $this->numero_carte;
    }

    public function setNumero_carte(string $value): void
    {
        $this->numero_carte = $value;
    }

    #[ORM\OneToMany(mappedBy: "creator_id", targetEntity: Forums::class)]
    private Collection $forumss;

        public function getForumss(): Collection
        {
            return $this->forumss;
        }
    
        public function addForums(Forums $forums): self
        {
            if (!$this->forumss->contains($forums)) {
                $this->forumss[] = $forums;
                $forums->setCreator_id($this);
            }
    
            return $this;
        }
    
        public function removeForums(Forums $forums): self
        {
            if ($this->forumss->removeElement($forums)) {
                // set the owning side to null (unless already changed)
                if ($forums->getCreator_id() === $this) {
                    $forums->setCreator_id(null);
                }
            }
    
            return $this;
        }

    #[ORM\OneToMany(mappedBy: "author_id", targetEntity: Posts::class)]
    private Collection $postss;

        public function getPostss(): Collection
        {
            return $this->postss;
        }
    
        public function addPosts(Posts $posts): self
        {
            if (!$this->postss->contains($posts)) {
                $this->postss[] = $posts;
                $posts->setAuthor_id($this);
            }
    
            return $this;
        }
    
        public function removePosts(Posts $posts): self
        {
            if ($this->postss->removeElement($posts)) {
                // set the owning side to null (unless already changed)
                if ($posts->getAuthor_id() === $this) {
                    $posts->setAuthor_id(null);
                }
            }
    
            return $this;
        }

    #[ORM\OneToMany(mappedBy: "author_id", targetEntity: Comments::class)]
    private Collection $commentss;

    #[ORM\OneToMany(mappedBy: "user_id", targetEntity: Forum_recommendations::class)]
    private Collection $forum_recommendationss;

        public function getForum_recommendationss(): Collection
        {
            return $this->forum_recommendationss;
        }
    
        public function addForum_recommendations(Forum_recommendations $forum_recommendations): self
        {
            if (!$this->forum_recommendationss->contains($forum_recommendations)) {
                $this->forum_recommendationss[] = $forum_recommendations;
                $forum_recommendations->setUser_id($this);
            }
    
            return $this;
        }
    
        public function removeForum_recommendations(Forum_recommendations $forum_recommendations): self
        {
            if ($this->forum_recommendationss->removeElement($forum_recommendations)) {
                // set the owning side to null (unless already changed)
                if ($forum_recommendations->getUser_id() === $this) {
                    $forum_recommendations->setUser_id(null);
                }
            }
    
            return $this;
        }

    #[ORM\OneToMany(mappedBy: "user_id", targetEntity: Shared_posts::class)]
    private Collection $shared_postss;

    #[ORM\OneToMany(mappedBy: "user_id", targetEntity: User_forum::class)]
    private Collection $user_forums;

    #[ORM\OneToMany(mappedBy: "user_id", targetEntity: User_interactions::class)]
    private Collection $user_interactionss;

    #[ORM\OneToMany(mappedBy: "user_id", targetEntity: Votes::class)]
    private Collection $votess;

    #[ORM\OneToMany(mappedBy: "user_id", targetEntity: User_badges::class)]
    private Collection $user_badgess;
}
