<?php

namespace App\Entity;

use App\Repository\ReservationDetailRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationDetailRepository::class)]
class ReservationDetail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $quantity = null;

    #[ORM\Column]
    private ?float $subtotal = null;

    #[ORM\ManyToOne(inversedBy: 'reservationDetails')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Flower $flower = null;

    #[ORM\ManyToOne(inversedBy: 'reservationDetails')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Reservation $reservation = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

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

    public function getFlower(): ?Flower
    {
        return $this->flower;
    }

    public function setFlower(?Flower $flower): static
    {
        $this->flower = $flower;

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
