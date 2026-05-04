<?php

namespace App\Entity;

use App\Repository\BouquetItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BouquetItemRepository::class)]
class BouquetItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Bouquet $bouquet = null;

    #[ORM\ManyToOne(inversedBy: 'bouquetItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Flower $flower = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Quantity is required.")]
    #[Assert\GreaterThan(value: 0, message: "Quantity must be at least 1.")]
    private ?int $quantity = null;

    /**
     * Price per unit at the time the item was added (snapshot).
     */
    #[ORM\Column]
    private ?float $unitPrice = null;

    #[ORM\Column]
    private ?float $subtotal = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBouquet(): ?Bouquet
    {
        return $this->bouquet;
    }

    public function setBouquet(?Bouquet $bouquet): static
    {
        $this->bouquet = $bouquet;

        return $this;
    }

    public function getFlower(): ?Flower
    {
        return $this->flower;
    }

    public function setFlower(?Flower $flower): static
    {
        $this->flower = $flower;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        $this->recalculateSubtotal();

        return $this;
    }

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(float $unitPrice): static
    {
        $this->unitPrice = $unitPrice;
        $this->recalculateSubtotal();

        return $this;
    }

    public function getSubtotal(): ?float
    {
        return $this->subtotal;
    }

    public function setSubtotal(float $subtotal): static
    {
        $this->subtotal = $subtotal;

        return $this;
    }

    /**
     * Recalculate subtotal = unitPrice × quantity.
     */
    public function recalculateSubtotal(): void
    {
        if ($this->unitPrice !== null && $this->quantity !== null) {
            $this->subtotal = $this->unitPrice * $this->quantity;
        }
    }
}
