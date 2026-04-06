<?php

namespace App\Model;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Same fields as creating a project; deadline may stay in the past when editing.
 */
class EditProjectRequest
{
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(min: 3, max: 150, minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.', maxMessage: 'Le titre ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $title = null;

    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(min: 10, max: 10000, minMessage: 'La description doit contenir au moins {{ limit }} caractères.', maxMessage: 'La description est trop longue.')]
    private ?string $description = null;

    #[Assert\NotBlank(message: "L'objectif de financement est obligatoire.")]
    #[Assert\Type(type: 'numeric', message: 'Le montant doit être un nombre.')]
    #[Assert\Positive(message: "L'objectif doit être supérieur à 0.")]
    private ?string $goalAmount = null;

    #[Assert\When(
        expression: 'this.getDeadline() !== null',
        constraints: [
            new Assert\Type(type: \DateTimeInterface::class, message: 'Date limite invalide.'),
        ]
    )]
    private ?\DateTimeInterface $deadline = null;

    #[Assert\Length(max: 100, maxMessage: 'La catégorie ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $category = null;

    #[Assert\When(
        expression: 'this.getImage() !== null',
        constraints: [
            new Assert\Image(
                maxSize: '5M',
                mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                mimeTypesMessage: 'Formats acceptés : JPEG, PNG, WebP.'
            ),
        ]
    )]
    private ?UploadedFile $image = null;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getGoalAmount(): ?string
    {
        return $this->goalAmount;
    }

    public function setGoalAmount(?string $goalAmount): void
    {
        $this->goalAmount = $goalAmount;
    }

    public function getDeadline(): ?\DateTimeInterface
    {
        return $this->deadline;
    }

    public function setDeadline(?\DateTimeInterface $deadline): void
    {
        $this->deadline = $deadline;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): void
    {
        $this->category = $category;
    }

    public function getImage(): ?UploadedFile
    {
        return $this->image;
    }

    public function setImage(?UploadedFile $image): void
    {
        $this->image = $image;
    }
}
