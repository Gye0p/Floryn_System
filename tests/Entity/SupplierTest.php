<?php

namespace App\Tests\Entity;

use App\Entity\Supplier;
use App\Entity\Flower;
use PHPUnit\Framework\TestCase;

class SupplierTest extends TestCase
{
    private Supplier $supplier;

    protected function setUp(): void
    {
        $this->supplier = new Supplier();
    }

    public function testNewSupplierHasNullId(): void
    {
        $this->assertNull($this->supplier->getId());
    }

    public function testSetSupplierName(): void
    {
        $this->supplier->setSupplierName('Rose Garden Co.');
        $this->assertSame('Rose Garden Co.', $this->supplier->getSupplierName());
    }

    public function testSetContactPerson(): void
    {
        $this->supplier->setContactPerson('Maria Santos');
        $this->assertSame('Maria Santos', $this->supplier->getContactPerson());
    }

    public function testSetPhone(): void
    {
        $this->supplier->setPhone('+639987654321');
        $this->assertSame('+639987654321', $this->supplier->getPhone());
    }

    public function testSetEmail(): void
    {
        $this->supplier->setEmail('supplier@example.com');
        $this->assertSame('supplier@example.com', $this->supplier->getEmail());
    }

    public function testSetAddress(): void
    {
        $this->supplier->setAddress('456 Flower Lane');
        $this->assertSame('456 Flower Lane', $this->supplier->getAddress());
    }

    public function testSetDeliverySchedule(): void
    {
        $this->supplier->setDeliverySchedule('Mon, Wed, Fri');
        $this->assertSame('Mon, Wed, Fri', $this->supplier->getDeliverySchedule());
    }

    public function testSetDateAdded(): void
    {
        $date = new \DateTime('2024-01-15');
        $this->supplier->setDateAdded($date);
        $this->assertSame($date, $this->supplier->getDateAdded());
    }

    public function testFlowersCollectionIsEmptyByDefault(): void
    {
        $this->assertCount(0, $this->supplier->getFlowers());
    }

    public function testAddFlower(): void
    {
        $flower = new Flower();
        $this->supplier->addFlower($flower);

        $this->assertCount(1, $this->supplier->getFlowers());
        $this->assertSame($this->supplier, $flower->getSupplier());
    }

    public function testRemoveFlower(): void
    {
        $flower = new Flower();
        $this->supplier->addFlower($flower);
        $this->supplier->removeFlower($flower);

        $this->assertCount(0, $this->supplier->getFlowers());
    }

    public function testAddDuplicateFlowerDoesNotDuplicate(): void
    {
        $flower = new Flower();
        $this->supplier->addFlower($flower);
        $this->supplier->addFlower($flower);

        $this->assertCount(1, $this->supplier->getFlowers());
    }

    public function testFluentInterface(): void
    {
        $result = $this->supplier
            ->setSupplierName('Test Supplier')
            ->setContactPerson('John')
            ->setPhone('123')
            ->setEmail('test@test.com')
            ->setAddress('Address')
            ->setDeliverySchedule('Daily');

        $this->assertInstanceOf(Supplier::class, $result);
    }
}
