<?php

namespace App\Tests\Entity;

use App\Entity\Flower;
use App\Entity\Supplier;
use App\Entity\InventoryLog;
use App\Entity\ReservationDetail;
use PHPUnit\Framework\TestCase;

class FlowerTest extends TestCase
{
    private Flower $flower;

    protected function setUp(): void
    {
        $this->flower = new Flower();
    }

    public function testNewFlowerHasNullId(): void
    {
        $this->assertNull($this->flower->getId());
    }

    public function testSetName(): void
    {
        $this->flower->setName('Red Rose');
        $this->assertSame('Red Rose', $this->flower->getName());
    }

    public function testSetCategory(): void
    {
        $this->flower->setCategory('Bouquet Flowers');
        $this->assertSame('Bouquet Flowers', $this->flower->getCategory());
    }

    public function testSetPrice(): void
    {
        $this->flower->setPrice(150.50);
        $this->assertSame(150.50, $this->flower->getPrice());
    }

    public function testSetDiscountPrice(): void
    {
        $this->flower->setDiscountPrice(120.40);
        $this->assertSame(120.40, $this->flower->getDiscountPrice());
    }

    public function testNullDiscountPrice(): void
    {
        $this->flower->setDiscountPrice(null);
        $this->assertNull($this->flower->getDiscountPrice());
    }

    public function testSetStockQuantity(): void
    {
        $this->flower->setStockQuantity(25);
        $this->assertSame(25, $this->flower->getStockQuantity());
    }

    public function testSetFreshnessStatus(): void
    {
        $this->flower->setFreshnessStatus('Fresh');
        $this->assertSame('Fresh', $this->flower->getFreshnessStatus());
    }

    public function testSetDateReceived(): void
    {
        $date = new \DateTime('2025-01-15');
        $this->flower->setDateReceived($date);
        $this->assertSame($date, $this->flower->getDateReceived());
    }

    public function testSetExpiryDate(): void
    {
        $date = new \DateTime('2025-02-15');
        $this->flower->setExpiryDate($date);
        $this->assertSame($date, $this->flower->getExpiryDate());
    }

    public function testSetStatus(): void
    {
        $this->flower->setStatus('Available');
        $this->assertSame('Available', $this->flower->getStatus());
    }

    public function testSetSupplier(): void
    {
        $supplier = new Supplier();
        $supplier->setSupplierName('Test Supplier');

        $this->flower->setSupplier($supplier);
        $this->assertSame($supplier, $this->flower->getSupplier());
    }

    public function testInventoryLogsCollection(): void
    {
        $this->assertCount(0, $this->flower->getInventoryLogs());
    }

    public function testAddInventoryLog(): void
    {
        $log = new InventoryLog();
        $this->flower->addInventoryLog($log);

        $this->assertCount(1, $this->flower->getInventoryLogs());
        $this->assertSame($this->flower, $log->getFlower());
    }

    public function testRemoveInventoryLog(): void
    {
        $log = new InventoryLog();
        $this->flower->addInventoryLog($log);
        $this->flower->removeInventoryLog($log);

        $this->assertCount(0, $this->flower->getInventoryLogs());
    }

    public function testReservationDetailsCollection(): void
    {
        $this->assertCount(0, $this->flower->getReservationDetails());
    }

    public function testAddReservationDetail(): void
    {
        $detail = new ReservationDetail();
        $this->flower->addReservationDetail($detail);

        $this->assertCount(1, $this->flower->getReservationDetails());
        $this->assertSame($this->flower, $detail->getFlower());
    }

    public function testFluentInterface(): void
    {
        $result = $this->flower
            ->setName('Tulip')
            ->setCategory('Garden Flowers')
            ->setPrice(100.0)
            ->setStockQuantity(10)
            ->setFreshnessStatus('Fresh')
            ->setStatus('Available');

        $this->assertInstanceOf(Flower::class, $result);
    }
}
