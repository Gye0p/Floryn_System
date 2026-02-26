<?php

namespace App\Entity;

use App\Repository\CustomerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CustomerRepository::class)]
class Customer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Full name is required.")]
    #[Assert\Regex(
        pattern: '/^[A-Za-z\s]+$/',
        message: "Name can only contain letters and spaces."
    )]
    private ?string $fullName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Phone number is required.")]
    #[Assert\Regex(
        pattern: '/^\+63[0-9]{10}$/',
        message: "Phone number must start with +63 and have 10 digits (e.g. +639123456789)."
    )]
    private ?string $phone = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Email is required.")]
    #[Assert\Email(message: "Please enter a valid email address.")]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    #[ORM\Column]
    private ?\DateTime $dateRegistered = null;

    /**
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'customer')]
    private Collection $reservations;

    /**
     * @var Collection<int, NotificationLog>
     */
    #[ORM\OneToMany(targetEntity: NotificationLog::class, mappedBy: 'customer')]
    private Collection $notificationLogs;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
        $this->notificationLogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getDateRegistered(): ?\DateTime
    {
        return $this->dateRegistered;
    }

    public function setDateRegistered(\DateTime $dateRegistered): static
    {
        $this->dateRegistered = $dateRegistered;

        return $this;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setCustomer($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        if ($this->reservations->removeElement($reservation)) {
            // set the owning side to null (unless already changed)
            if ($reservation->getCustomer() === $this) {
                $reservation->setCustomer(null);
            }
        }

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
            $notificationLog->setCustomer($this);
        }

        return $this;
    }

    public function removeNotificationLog(NotificationLog $notificationLog): static
    {
        if ($this->notificationLogs->removeElement($notificationLog)) {
            // set the owning side to null (unless already changed)
            if ($notificationLog->getCustomer() === $this) {
                $notificationLog->setCustomer(null);
            }
        }

        return $this;
    }
}
