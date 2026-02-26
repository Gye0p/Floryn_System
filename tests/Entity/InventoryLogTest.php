<?php

namespace App\Tests\Entity;

use App\Entity\InventoryLog;
use App\Entity\Flower;
use PHPUnit\Framework\TestCase;

class InventoryLogTest extends TestCase
{
    private InventoryLog $log;

    protected function setUp(): void
    {
        $this->log = new InventoryLog();
    }

    public function testNewLogHasNullId(): void
    {
        $this->assertNull($this->log->getId());
    }

    public function testSetQuantityIn(): void
    {
        $this->log->setQuantityIn(50);
        $this->assertSame(50, $this->log->getQuantityIn());
    }

    public function testSetQuantityOut(): void
    {
        $this->log->setQuantityOut(10);
        $this->assertSame(10, $this->log->getQuantityOut());
    }

    public function testSetDateUpdated(): void
    {
        $date = new \DateTime('2025-06-10');
        $this->log->setDateUpdated($date);
        $this->assertSame($date, $this->log->getDateUpdated());
    }

    public function testSetRemarks(): void
    {
        $this->log->setRemarks('Restocked from supplier delivery');
        $this->assertSame('Restocked from supplier delivery', $this->log->getRemarks());
    }

    public function testSetFlower(): void
    {
        $flower = new Flower();
        $this->log->setFlower($flower);
        $this->assertSame($flower, $this->log->getFlower());
    }

    public function testSetNullFlower(): void
    {
        $this->log->setFlower(null);
        $this->assertNull($this->log->getFlower());
    }

    public function testFluentInterface(): void
    {
        $result = $this->log
            ->setQuantityIn(20)
            ->setQuantityOut(0)
            ->setDateUpdated(new \DateTime())
            ->setRemarks('Test');

        $this->assertInstanceOf(InventoryLog::class, $result);
    }
}
