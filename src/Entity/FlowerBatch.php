<?php

namespace App\Entity;

use App\Repository\FlowerBatchRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Tracks individual delivery batches for each flower.
 * Each time you receive stock of a flower, a new batch is created
 * with its own quantity, date received, and expiry date.
 *
 * This enables:
 *  - Multiple expiry dates for the same flower (different deliveries)
 *  - FIFO selling (oldest batch first)
 *  - Accurate freshness tracking per batch
 *  - Traceability back to supplier deliveries
 */
#[ORM\Entity(repositoryClass: FlowerBatchRepository::class)]
#[ORM\HasLifecycleCallbacks]
class FlowerBatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'batches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Flower $flower = null;

    /**
     * Original quantity received in this batch.
     */
    #[ORM\Column]
    #[Assert\NotBlank(message: "Quantity is required.")]
    #[Assert\GreaterThan(value: 0, message: "Quantity must be greater than zero.")]
    private ?int $quantityReceived = null;

    /**
     * Remaining quantity available for sale.
     * Decremented as items are sold via POS or reservations.
     */
    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(value: 0, message: "Remaining quantity cannot be negative.")]
    private ?int $quantityRemaining = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: "Date received is required.")]
    private ?\DateTime $dateReceived = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: "Expiry date is required.")]
    #[Assert\GreaterThan(
        propertyPath: "dateReceived",
        message: "Expiry date must be after the date received."
    )]
    private ?\DateTime $expiryDate = null;

    /**
     * Freshness status for THIS batch, computed from its own expiry date.
     * Values: Fresh, Good, Last Sale, Expired
     */
    #[ORM\Column(length: 50)]
    private ?string $freshnessStatus = 'Fresh';

    /**
     * Whether this batch is still active (has remaining stock and not expired).
     */
    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $createdAt = null;

    // ─── Getters & Setters ───

    public function getId(): ?int
    {
        return $this->id;
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

    public function getQuantityReceived(): ?int
    {
        return $this->quantityReceived;
    }

    public function setQuantityReceived(int $quantityReceived): static
    {
        $this->quantityReceived = $quantityReceived;
        return $this;
    }

    public function getQuantityRemaining(): ?int
    {
        return $this->quantityRemaining;
    }

    public function setQuantityRemaining(int $quantityRemaining): static
    {
        $this->quantityRemaining = max(0, $quantityRemaining);

        // Auto-deactivate when empty
        if ($this->quantityRemaining <= 0) {
            $this->active = false;
        }

        return $this;
    }

    /**
     * Deduct quantity from this batch. Returns the amount actually deducted
     * (may be less than requested if batch doesn't have enough).
     */
    public function deduct(int $amount): int
    {
        $canDeduct = min($amount, $this->quantityRemaining);
        $this->setQuantityRemaining($this->quantityRemaining - $canDeduct);
        return $canDeduct;
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

    public function getFreshnessStatus(): ?string
    {
        return $this->freshnessStatus;
    }

    public function setFreshnessStatus(string $freshnessStatus): static
    {
        $this->freshnessStatus = $freshnessStatus;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        if ($this->quantityRemaining === null) {
            $this->quantityRemaining = $this->quantityReceived;
        }
    }

    /**
     * Calculate days until expiry from today.
     */
    public function getDaysUntilExpiry(): int
    {
        $today = new \DateTime('today');
        $interval = $today->diff($this->expiryDate);
        return $interval->invert ? -$interval->days : $interval->days;
    }

    public function isExpired(): bool
    {
        return $this->getDaysUntilExpiry() < 0;
    }
}
