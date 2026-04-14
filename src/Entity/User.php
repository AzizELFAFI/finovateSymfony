<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity]
#[ORM\Table(name: 'user')]
#[Vich\Uploadable]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{

    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    private string $id;

    #[ORM\Column(type: "string", length: 255)]
    #[Assert\NotBlank(message: "L'adresse e-mail est obligatoire.")]
    #[Assert\Email(message: "Veuillez saisir une adresse e-mail valide.")]
    private string $email;

    #[ORM\Column(type: "string", length: 255)]
    #[Assert\NotBlank(message: "Le mot de passe est obligatoire.")]
    private string $password;

    #[ORM\Column(type: "string", length: 100)]
    #[Assert\NotBlank(message: "Le prénom est obligatoire.")]
    #[Assert\Length(min: 3, minMessage: "Le prénom doit contenir au moins {{ limit }} caractères.")]
    #[Assert\Regex(pattern: "/^[\\p{L} .'-]+$/u", message: "Le prénom doit être une chaîne de caractères valide.")]
    private string $firstname;

    #[ORM\Column(type: "string", length: 100)]
    #[Assert\NotBlank(message: "Le nom est obligatoire.")]
    #[Assert\Length(min: 3, minMessage: "Le nom doit contenir au moins {{ limit }} caractères.")]
    #[Assert\Regex(pattern: "/^[\\p{L} .'-]+$/u", message: "Le nom doit être une chaîne de caractères valide.")]
    private string $lastname;

    #[ORM\Column(type: "string", length: 50)]
    private string $role;

    #[ORM\Column(type: "integer")]
    private int $points;

    #[ORM\Column(type: "string", length: 255)]
    private string $solde;

    #[ORM\Column(type: "date")]
    #[Assert\NotBlank(message: "La date de naissance est obligatoire.")]
    #[Assert\Type(type: "\\DateTimeInterface", message: "Date de naissance invalide.")]
    #[Assert\LessThanOrEqual(value: "-18 years", message: "Vous devez avoir au moins 18 ans pour créer un compte.")]
    private \DateTimeInterface $birthdate;

    #[ORM\Column(type: "string", length: 50)]
    #[Assert\NotBlank(message: "Le CIN est obligatoire.")]
    #[Assert\Regex(pattern: "/^\\d{8}$/", message: "Le CIN doit contenir exactement 8 chiffres.")]
    private string $cin;

    #[ORM\Column(type: "integer")]
    #[Assert\NotBlank(message: "Le numéro de téléphone est obligatoire.")]
    #[Assert\Positive(message: "Le numéro de téléphone doit être numérique.")]
    #[Assert\Range(min: 10000000, max: 99999999, notInRangeMessage: "Le numéro de téléphone doit contenir exactement 8 chiffres.")]
    private int $phone_number;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: "bigint")]
    private string $numero_carte;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $imageName = null;

    #[Vich\UploadableField(mapping: 'profile_images', fileNameProperty: 'imageName')]
    private ?File $imageFile = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $faceAuthEnabled = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $faceEmbedding = null;

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

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): void
    {
        $this->isVerified = $isVerified;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    public function setImageName(?string $imageName): void
    {
        $this->imageName = $imageName;
    }

    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;

        if ($imageFile !== null) {
            $this->updatedAt = new \DateTime();
        }
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function isFaceAuthEnabled(): bool
    {
        return $this->faceAuthEnabled;
    }

    public function setFaceAuthEnabled(bool $enabled): void
    {
        $this->faceAuthEnabled = $enabled;
    }

    public function getFaceEmbedding(): ?string
    {
        return $this->faceEmbedding;
    }

    public function setFaceEmbedding(?string $embedding): void
    {
        $this->faceEmbedding = $embedding;
    }

    #[ORM\OneToMany(mappedBy: "creator", targetEntity: Forum::class)]
    private Collection $forums;

        public function getForums(): Collection
        {
            return $this->forums;
        }
    
        public function addForum(Forum $forum): self
        {
            if (!$this->forums->contains($forum)) {
                $this->forums[] = $forum;
                $forum->setCreator($this);
            }
    
            return $this;
        }
    
        public function removeForum(Forum $forum): self
        {
            if ($this->forums->removeElement($forum)) {
                if ($forum->getCreator() === $this) {
                    $forum->setCreator(null);
                }
            }
    
            return $this;
        }

    #[ORM\OneToMany(mappedBy: "author", targetEntity: Post::class)]
    private Collection $posts;

        public function getPosts(): Collection
        {
            return $this->posts;
        }
    
        public function addPost(Post $post): self
        {
            if (!$this->posts->contains($post)) {
                $this->posts[] = $post;
                $post->setAuthor($this);
            }
    
            return $this;
        }
    
        public function removePost(Post $post): self
        {
            if ($this->posts->removeElement($post)) {
                if ($post->getAuthor() === $this) {
                    $post->setAuthor(null);
                }
            }
    
            return $this;
        }

    #[ORM\OneToMany(mappedBy: "author", targetEntity: Comment::class)]
    private Collection $comments;

    public function getComments(): Collection
    {
        return $this->comments;
    }

    #[ORM\OneToMany(mappedBy: "user", targetEntity: ForumRecommendation::class)]
    private Collection $forumRecommendations;

    #[ORM\OneToMany(mappedBy: "user", targetEntity: SharedPost::class)]
    private Collection $sharedPosts;

    #[ORM\OneToMany(mappedBy: "user", targetEntity: UserForum::class)]
    private Collection $userForums;

    #[ORM\OneToMany(mappedBy: "user", targetEntity: UserInteraction::class)]
    private Collection $userInteractions;

    #[ORM\OneToMany(mappedBy: "user", targetEntity: Vote::class)]
    private Collection $votes;

    #[ORM\OneToMany(mappedBy: "user", targetEntity: UserBadge::class)]
    private Collection $userBadges;

    /**
     * @var Collection<int, UserAdClick>
     */
    #[ORM\OneToMany(targetEntity: UserAdClick::class, mappedBy: 'user')]
    private Collection $userAdClicks;

    public function __construct()
    {
        $this->forums = new ArrayCollection();
        $this->posts = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->forumRecommendations = new ArrayCollection();
        $this->sharedPosts = new ArrayCollection();
        $this->userForums = new ArrayCollection();
        $this->userInteractions = new ArrayCollection();
        $this->votes = new ArrayCollection();
        $this->userBadges = new ArrayCollection();
        $this->userAdClicks = new ArrayCollection();
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
            $userAdClick->setUser($this);
        }

        return $this;
    }

    public function removeUserAdClick(UserAdClick $userAdClick): static
    {
        if ($this->userAdClicks->removeElement($userAdClick)) {
            // set the owning side to null (unless already changed)
            if ($userAdClick->getUser() === $this) {
                $userAdClick->setUser(null);
            }
        }

        return $this;
    }
}
