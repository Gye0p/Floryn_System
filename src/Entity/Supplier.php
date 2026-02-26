<?php

namespace App\Entity;

use App\Repository\SupplierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SupplierRepository::class)]
class Supplier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Supplier name is required.")]
    #[Assert\Regex(
        pattern: '/^[A-Za-z\s&\-\.]+$/',
        message: "Supplier name can only contain letters, spaces, and basic punctuation (&, -, .)."
    )]
    private ?string $supplierName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Contact person is required.")]
    #[Assert\Regex(
        pattern: '/^[A-Za-z\s]+$/',
        message: "Contact person name can only contain letters and spaces."
    )]
    private ?string $contactPerson = null;

    #[ORM\Column(length: 50)]
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

    #[ORM\Column(type: Types::TEXT)]
    private ?string $address = null;

    #[ORM\Column(length: 255)]
    private ?string $deliverySchedule = null;

    #[ORM\Column]
    private ?\DateTime $dateAdded = null;

    /**
     * @var Collection<int, Flower>
     */
    #[ORM\OneToMany(targetEntity: Flower::class, mappedBy: 'supplier')]
    private Collection $flowers;

    public function __construct()
    {
        $this->flowers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSupplierName(): ?string
    {
        return $this->supplierName;
    }

    public function setSupplierName(string $supplierName): static
    {
        $this->supplierName = $supplierName;

        return $this;
    }

    public function getContactPerson(): ?string
    {
        return $this->contactPerson;
    }

    public function setContactPerson(string $contactPerson): static
    {
        $this->contactPerson = $contactPerson;

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

    public function getDeliverySchedule(): ?string
    {
        return $this->deliverySchedule;
    }

    public function setDeliverySchedule(string $deliverySchedule): static
    {
        $this->deliverySchedule = $deliverySchedule;

        return $this;
    }

    public function getDateAdded(): ?\DateTime
    {
        return $this->dateAdded;
    }

    public function setDateAdded(\DateTime $dateAdded): static
    {
        $this->dateAdded = $dateAdded;

        return $this;
    }

    /**
     * @return Collection<int, Flower>
     */
    public function getFlowers(): Collection
    {
        return $this->flowers;
    }

    public function addFlower(Flower $flower): static
    {
        if (!$this->flowers->contains($flower)) {
            $this->flowers->add($flower);
            $flower->setSupplier($this);
        }

        return $this;
    }

    public function removeFlower(Flower $flower): static
    {
        if ($this->flowers->removeElement($flower)) {
            // set the owning side to null (unless already changed)
            if ($flower->getSupplier() === $this) {
                $flower->setSupplier(null);
            }
        }

        return $this;
    }
}
