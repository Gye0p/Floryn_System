<?php

namespace App\Entity;

use App\Repository\InventoryLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryLogRepository::class)]
class InventoryLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $quantityIn = null;

    #[ORM\Column]
    private ?int $quantityOut = null;

    #[ORM\Column]
    private ?\DateTime $dateUpdated = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $remarks = null;

    #[ORM\ManyToOne(inversedBy: 'inventoryLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Flower $flower = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantityIn(): ?int
    {
        return $this->quantityIn;
    }

    public function setQuantityIn(int $quantityIn): static
    {
        $this->quantityIn = $quantityIn;

        return $this;
    }

    public function getQuantityOut(): ?int
    {
        return $this->quantityOut;
    }

    public function setQuantityOut(int $quantityOut): static
    {
        $this->quantityOut = $quantityOut;

        return $this;
    }

    public function getDateUpdated(): ?\DateTime
    {
        return $this->dateUpdated;
    }

    public function setDateUpdated(\DateTime $dateUpdated): static
    {
        $this->dateUpdated = $dateUpdated;

        return $this;
    }

    public function getRemarks(): ?string
    {
        return $this->remarks;
    }

    public function setRemarks(string $remarks): static
    {
        $this->remarks = $remarks;

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
}
