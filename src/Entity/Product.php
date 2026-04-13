<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du produit est requis')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le nom doit contenir au moins 3 caractères',
        maxMessage: 'Le nom ne doit pas dépasser 255 caractères'
    )]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 5000,
        maxMessage: 'La description ne doit pas dépasser 5000 caractères'
    )]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Le prix en points est requis')]
    #[Assert\Positive(message: 'Le prix doit être positive')]
    #[Assert\Range(
        min: 1,
        max: 1000000,
        notInRangeMessage: 'Le prix doit être entre 1 et 1000000'
    )]
    private ?int $pricePoints = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Le stock est requis')]
    #[Assert\GreaterThanOrEqual(
        value: 0,
        message: 'Le stock ne peut pas être négatif'
    )]
    #[Assert\Range(
        min: 0,
        max: 1000000,
        notInRangeMessage: 'Le stock doit être entre 0 et 1000000'
    )]
    private ?int $stock = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPricePoints(): ?int
    {
        return $this->pricePoints;
    }

    public function setPricePoints(int $pricePoints): static
    {
        $this->pricePoints = $pricePoints;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }
}
