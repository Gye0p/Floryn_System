<?php

namespace App\Entity;

use App\Repository\BouquetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BouquetRepository::class)]
class Bouquet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Bouquet name is required.")]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * Status: 'Draft' | 'Ready' | 'Sold'
     */
    #[ORM\Column(length: 50)]
    private string $status = 'Draft';

    #[ORM\Column]
    private ?float $totalPrice = 0.0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $createdAt = null;

    /**
     * @var Collection<int, BouquetItem>
     */
    #[ORM\OneToMany(targetEntity: BouquetItem::class, mappedBy: 'bouquet', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTotalPrice(): ?float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): static
    {
        $this->totalPrice = $totalPrice;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, BouquetItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(BouquetItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setBouquet($this);
        }

        return $this;
    }

    public function removeItem(BouquetItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getBouquet() === $this) {
                $item->setBouquet(null);
            }
        }

        return $this;
    }

    /**
     * Recalculate and sync totalPrice from all items.
     */
    public function recalculateTotalPrice(): void
    {
        $total = 0.0;
        foreach ($this->items as $item) {
            $total += $item->getSubtotal();
        }
        $this->totalPrice = $total;
    }
}
