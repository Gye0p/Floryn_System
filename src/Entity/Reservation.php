<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: "Pickup date is required.")]
    #[Assert\GreaterThanOrEqual(
        "today",
        message: "Pickup date must not be in the past."
    )]
    private ?\DateTime $pickupDate = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Total amount is required.")]
    #[Assert\Type(type: "numeric", message: "Total amount must be a number.")]
    #[Assert\GreaterThan(value: 0, message: "Total amount must be greater than zero.")]
    private ?float $totalAmount = null;

    #[ORM\Column(length: 255)]
    private ?string $paymentStatus = null;

    #[ORM\Column(length: 255)]
    private ?string $reservationStatus = null;

    #[ORM\Column]
    private ?\DateTime $dateReserved = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Customer $customer = null;

    /**
     * @var Collection<int, ReservationDetail>
     */
    #[ORM\OneToMany(targetEntity: ReservationDetail::class, mappedBy: 'reservation')]
    private Collection $reservationDetails;

    #[ORM\OneToOne(mappedBy: 'reservation', cascade: ['persist', 'remove'])]
    private ?Payment $payment = null;

    /**
     * @var Collection<int, NotificationLog>
     */
    #[ORM\OneToMany(targetEntity: NotificationLog::class, mappedBy: 'reservation')]
    private Collection $notificationLogs;

    public function __construct()
    {
        $this->reservationDetails = new ArrayCollection();
        $this->notificationLogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPickupDate(): ?\DateTime
    {
        return $this->pickupDate;
    }

    public function setPickupDate(\DateTime $pickupDate): static
    {
        $this->pickupDate = $pickupDate;

        return $this;
    }

    public function getTotalAmount(): ?float
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(float $totalAmount): static
    {
        $this->totalAmount = $totalAmount;

        return $this;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $paymentStatus): static
    {
        $this->paymentStatus = $paymentStatus;

        return $this;
    }

    public function getReservationStatus(): ?string
    {
        return $this->reservationStatus;
    }

    public function setReservationStatus(string $reservationStatus): static
    {
        $this->reservationStatus = $reservationStatus;

        return $this;
    }

    public function getDateReserved(): ?\DateTime
    {
        return $this->dateReserved;
    }

    public function setDateReserved(\DateTime $dateReserved): static
    {
        $this->dateReserved = $dateReserved;

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

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
            $reservationDetail->setReservation($this);
        }

        return $this;
    }

    public function removeReservationDetail(ReservationDetail $reservationDetail): static
    {
        if ($this->reservationDetails->removeElement($reservationDetail)) {
            // set the owning side to null (unless already changed)
            if ($reservationDetail->getReservation() === $this) {
                $reservationDetail->setReservation(null);
            }
        }

        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(Payment $payment): static
    {
        // set the owning side of the relation if necessary
        if ($payment->getReservation() !== $this) {
            $payment->setReservation($this);
        }

        $this->payment = $payment;

        return $this;
    }

    /**
     * @return Collection<int, NotificationLog>
     */
    public function getNotificationLogs(): Collection
    {
        return $this->notificationLogs;
    }

    public function addNotificationLog(NotificationLog $notificationLog): static
    {
        if (!$this->notificationLogs->contains($notificationLog)) {
            $this->notificationLogs->add($notificationLog);
            $notificationLog->setReservation($this);
        }

        return $this;
    }

    public function removeNotificationLog(NotificationLog $notificationLog): static
    {
        if ($this->notificationLogs->removeElement($notificationLog)) {
            // set the owning side to null (unless already changed)
            if ($notificationLog->getReservation() === $this) {
                $notificationLog->setReservation(null);
            }
        }

        return $this;
    }
}
