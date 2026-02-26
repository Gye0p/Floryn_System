<?php

namespace App\Tests\Entity;

use App\Entity\ReservationDetail;
use App\Entity\Reservation;
use App\Entity\Flower;
use PHPUnit\Framework\TestCase;

class ReservationDetailTest extends TestCase
{
    private ReservationDetail $detail;

    protected function setUp(): void
    {
        $this->detail = new ReservationDetail();
    }

    public function testNewDetailHasNullId(): void
    {
        $this->assertNull($this->detail->getId());
    }

    public function testSetQuantity(): void
    {
        $this->detail->setQuantity(10);
        $this->assertSame(10, $this->detail->getQuantity());
    }

    public function testSetSubtotal(): void
    {
        $this->detail->setSubtotal('1500.00');
        $this->assertEquals(1500.00, $this->detail->getSubtotal());
    }

    public function testSetFlower(): void
    {
        $flower = new Flower();
        $this->detail->setFlower($flower);
        $this->assertSame($flower, $this->detail->getFlower());
    }

    public function testSetNullFlower(): void
    {
        $this->detail->setFlower(null);
        $this->assertNull($this->detail->getFlower());
    }

    public function testSetReservation(): void
    {
        $reservation = new Reservation();
        $this->detail->setReservation($reservation);
        $this->assertSame($reservation, $this->detail->getReservation());
    }

    public function testSetNullReservation(): void
    {
        $this->detail->setReservation(null);
        $this->assertNull($this->detail->getReservation());
    }

    public function testFluentInterface(): void
    {
        $result = $this->detail
            ->setQuantity(5)
            ->setSubtotal('750.00');

        $this->assertInstanceOf(ReservationDetail::class, $result);
    }
}
