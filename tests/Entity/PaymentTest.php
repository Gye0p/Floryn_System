<?php

namespace App\Tests\Entity;

use App\Entity\Payment;
use App\Entity\Reservation;
use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase
{
    private Payment $payment;

    protected function setUp(): void
    {
        $this->payment = new Payment();
    }

    public function testNewPaymentHasNullId(): void
    {
        $this->assertNull($this->payment->getId());
    }

    public function testSetPaymentDate(): void
    {
        $date = new \DateTime('2025-06-10');
        $this->payment->setPaymentDate($date);
        $this->assertSame($date, $this->payment->getPaymentDate());
    }

    public function testSetAmountPaid(): void
    {
        $this->payment->setAmountPaid('2500.00');
        $this->assertEquals(2500.00, $this->payment->getAmountPaid());
    }

    public function testSetPaymentMethod(): void
    {
        $this->payment->setPaymentMethod('GCash');
        $this->assertSame('GCash', $this->payment->getPaymentMethod());
    }

    public function testSetReferenceNo(): void
    {
        $this->payment->setReferenceNo('REF-12345');
        $this->assertSame('REF-12345', $this->payment->getReferenceNo());
    }

    public function testDefaultReferenceNoIsNull(): void
    {
        $this->assertNull($this->payment->getReferenceNo());
    }

    public function testSetStatus(): void
    {
        $this->payment->setStatus('Completed');
        $this->assertSame('Completed', $this->payment->getStatus());
    }

    public function testSetReservation(): void
    {
        $reservation = new Reservation();
        $this->payment->setReservation($reservation);
        $this->assertSame($reservation, $this->payment->getReservation());
    }

    public function testDefaultReservationIsNull(): void
    {
        $this->assertNull($this->payment->getReservation());
    }

    public function testFluentInterface(): void
    {
        $result = $this->payment
            ->setPaymentDate(new \DateTime())
            ->setAmountPaid('500')
            ->setPaymentMethod('Cash')
            ->setStatus('Pending');

        $this->assertInstanceOf(Payment::class, $result);
    }
}
