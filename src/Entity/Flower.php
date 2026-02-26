<?php

namespace App\Entity;

use App\Repository\FlowerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FlowerRepository::class)]
class Flower
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Flower name is required.")]
    #[Assert\Regex(
        pattern: '/^[A-Za-z\s\-\']+$/',
        message: "Flower name can only contain letters, spaces, hyphens, and apostrophes."
    )]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Category is required.")]
    #[Assert\Choice(
        choices: ['Bouquet Flowers', 'Tropical Flowers', 'Wedding Flowers', 'Funeral Flowers', 'Seasonal Flowers', 'Potted Plants', 'Garden Flowers', 'Exotic Flowers', 'Indoor Plants', 'Decorative Plants'],
        message: "Please select a valid flower category."
    )]
    private ?string $category = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Price is required.")]
    #[Assert\Type(type: "numeric", message: "Price must be a valid number.")]
    #[Assert\GreaterThan(value: 0, message: "Price must be greater than zero.")]
    private ?float $price = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\Type(type: "numeric", message: "Discount price must be a valid number.")]
    #[Assert\GreaterThanOrEqual(value: 0, message: "Discount price must be zero or greater.")]
    private ?string $discountPrice = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Stock quantity is required.")]
    #[Assert\Type(type: "integer", message: "Stock quantity must be a whole number.")]
    #[Assert\GreaterThanOrEqual(value: 0, message: "Stock quantity cannot be negative.")]
    private ?int $stockQuantity = null;

    #[ORM\Column(length: 50)]
    private ?string $freshnessStatus = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: "Date received is required.")]
    #[Assert\LessThanOrEqual(
        "today",
        message: "Date received cannot be in the future."
    )]
    private ?\DateTime $dateReceived = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: "Expiry date is required.")]
    #[Assert\GreaterThanOrEqual(
        "today",
        message: "Expiry date must be today or in the future."
    )]
    private ?\DateTime $expiryDate = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\ManyToOne(inversedBy: 'flowers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Supplier $supplier = null;

    /**
     * @var Collection<int, InventoryLog>
     */
    #[ORM\OneToMany(targetEntity: InventoryLog::class, mappedBy: 'flower')]
    private Collection $inventoryLogs;

    /**
     * @var Collection<int, ReservationDetail>
     */
    #[ORM\OneToMany(targetEntity: ReservationDetail::class, mappedBy: 'flower')]
    private Collection $reservationDetails;

    public function __construct()
    {
        $this->inventoryLogs = new ArrayCollection();
        $this->reservationDetails = new ArrayCollection();
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

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getDiscountPrice(): ?float
    {
        return $this->discountPrice ? (float) $this->discountPrice : null;
    }

    public function setDiscountPrice(?float $discountPrice): static
    {
        $this->discountPrice = $discountPrice ? (string) $discountPrice : null;

        return $this;
    }

    public function getStockQuantity(): ?int
    {
        return $this->stockQuantity;
    }

    public function setStockQuantity(int $stockQuantity): static
    {
        $this->stockQuantity = $stockQuantity;

        return $this;
    }

    public function getFreshnessStatus(): ?string
    {
        return $this->freshnessStatus;
    }

    public function setFreshnessStatus(string $freshnessStatus): static
    {
        $this->freshnessStatus = $freshnessStatus;

        return $this;
    }

    public function getDateReceived(): ?\DateTime
    {
        return $this->dateReceived;
    }

    public function setDateReceived(\DateTime $dateReceived): static
    {
        $this->dateReceived = $dateReceived;

        return $this;
    }

    public function getExpiryDate(): ?\DateTime
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(\DateTime $expiryDate): static
    {
        $this->expiryDate = $expiryDate;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(?Supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    /**
     * @return Collection<int, InventoryLog>
     */
    public function getInventoryLogs(): Collection
    {
        return $this->inventoryLogs;
    }

    public function addInventoryLog(InventoryLog $inventoryLog): static
    {
        if (!$this->inventoryLogs->contains($inventoryLog)) {
            $this->inventoryLogs->add($inventoryLog);
            $inventoryLog->setFlower($this);
        }

        return $this;
    }

    public function removeInventoryLog(InventoryLog $inventoryLog): static
    {
        if ($this->inventoryLogs->removeElement($inventoryLog)) {
            // set the owning side to null (unless already changed)
            if ($inventoryLog->getFlower() === $this) {
                $inventoryLog->setFlower(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ReservationDetail>
     */
    public function getReservationDetails(): Collection
    {
        return $this->reservationDetails;
    }

    public function addReservationDetail(ReservationDetail $reservationDetail): static
    {
        if (!$this->reservationDetails->contains($reservationDetail)) {
            $this->reservationDetails->add($reservationDetail);
            $reservationDetail->setFlower($this);
        }

        return $this;
    }

    public function removeReservationDetail(ReservationDetail $reservationDetail): static
    {
        if ($this->reservationDetails->removeElement($reservationDetail)) {
            // set the owning side to null (unless already changed)
            if ($reservationDetail->getFlower() === $this) {
                $reservationDetail->setFlower(null);
            }
        }

        return $this;
    }
}
