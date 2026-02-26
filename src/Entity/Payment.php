<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Payment date is required.")]
    #[Assert\LessThanOrEqual(
        "today",
        message: "Payment date cannot be in the future."
    )]
    private ?\DateTime $paymentDate = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Amount paid is required.")]
    #[Assert\Type(type: "numeric", message: "Amount paid must be a number.")]
    #[Assert\GreaterThan(value: 0, message: "Amount paid must be greater than zero.")]
    private ?float $amountPaid = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Payment method is required.")]
    #[Assert\Choice(
        choices: ['Cash', 'Credit Card', 'Debit Card', 'GCash', 'PayMaya', 'Bank Transfer'],
        message: "Please select a valid payment method."
    )]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Reference number is required.")]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: "Reference number must be at least 3 characters long.",
        maxMessage: "Reference number cannot exceed 50 characters."
    )]
    private ?string $referenceNo = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\OneToOne(inversedBy: 'payment', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Reservation $reservation = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPaymentDate(): ?\DateTime
    {
        return $this->paymentDate;
    }

    public function setPaymentDate(\DateTime $paymentDate): static
    {
        $this->paymentDate = $paymentDate;

        return $this;
    }

    public function getAmountPaid(): ?float
    {
        return $this->amountPaid;
    }

    public function setAmountPaid(float $amountPaid): static
    {
        $this->amountPaid = $amountPaid;

        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getReferenceNo(): ?string
    {
        return $this->referenceNo;
    }

    public function setReferenceNo(string $referenceNo): static
    {
        $this->referenceNo = $referenceNo;

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

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(Reservation $reservation): static
    {
        $this->reservation = $reservation;

        return $this;
    }
}
