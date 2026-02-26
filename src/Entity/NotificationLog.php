<?php

namespace App\Entity;

use App\Repository\NotificationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationLogRepository::class)]
class NotificationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column(length: 50)]
    private ?string $channel = null;

    #[ORM\Column]
    private ?\DateTime $dateSent = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\ManyToOne(inversedBy: 'notificationLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Customer $customer = null;

    #[ORM\ManyToOne(inversedBy: 'notificationLogs')]
    private ?Reservation $reservation = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function getDateSent(): ?\DateTime
    {
        return $this->dateSent;
    }

    public function setDateSent(\DateTime $dateSent): static
    {
        $this->dateSent = $dateSent;

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

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): static
    {
        $this->reservation = $reservation;

        return $this;
    }
}
