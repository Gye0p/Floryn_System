<?php

namespace App\Tests\Entity;

use App\Entity\Reservation;
use App\Entity\Customer;
use App\Entity\Payment;
use App\Entity\ReservationDetail;
use App\Entity\NotificationLog;
use PHPUnit\Framework\TestCase;

class ReservationTest extends TestCase
{
    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->reservation = new Reservation();
    }

    public function testNewReservationHasNullId(): void
    {
        $this->assertNull($this->reservation->getId());
    }

    public function testSetPickupDate(): void
    {
        $date = new \DateTime('2025-06-15');
        $this->reservation->setPickupDate($date);
        $this->assertSame($date, $this->reservation->getPickupDate());
    }

    public function testSetTotalAmount(): void
    {
        $this->reservation->setTotalAmount('1500.50');
        $this->assertEquals(1500.50, $this->reservation->getTotalAmount());
    }

    public function testSetPaymentStatus(): void
    {
        $this->reservation->setPaymentStatus('Paid');
        $this->assertSame('Paid', $this->reservation->getPaymentStatus());
    }

    public function testSetReservationStatus(): void
    {
        $this->reservation->setReservationStatus('Confirmed');
        $this->assertSame('Confirmed', $this->reservation->getReservationStatus());
    }

    public function testSetDateReserved(): void
    {
        $date = new \DateTime();
        $this->reservation->setDateReserved($date);
        $this->assertSame($date, $this->reservation->getDateReserved());
    }

    public function testSetCustomer(): void
    {
        $customer = new Customer();
        $this->reservation->setCustomer($customer);
        $this->assertSame($customer, $this->reservation->getCustomer());
    }

    public function testReservationDetailsCollection(): void
    {
        $this->assertCount(0, $this->reservation->getReservationDetails());
    }

    public function testAddReservationDetail(): void
    {
        $detail = new ReservationDetail();
        $this->reservation->addReservationDetail($detail);

        $this->assertCount(1, $this->reservation->getReservationDetails());
        $this->assertSame($this->reservation, $detail->getReservation());
    }

    public function testRemoveReservationDetail(): void
    {
        $detail = new ReservationDetail();
        $this->reservation->addReservationDetail($detail);
        $this->reservation->removeReservationDetail($detail);

        $this->assertCount(0, $this->reservation->getReservationDetails());
    }

    public function testSetPayment(): void
    {
        $payment = new Payment();
        $this->reservation->setPayment($payment);
        $this->assertSame($payment, $this->reservation->getPayment());
    }

    public function testDefaultPaymentIsNull(): void
    {
        $this->assertNull($this->reservation->getPayment());
    }

    public function testNotificationLogsCollection(): void
    {
        $this->assertCount(0, $this->reservation->getNotificationLogs());
    }

    public function testAddNotificationLog(): void
    {
        $log = new NotificationLog();
        $this->reservation->addNotificationLog($log);

        $this->assertCount(1, $this->reservation->getNotificationLogs());
        $this->assertSame($this->reservation, $log->getReservation());
    }

    public function testFluentInterface(): void
    {
        $result = $this->reservation
            ->setPickupDate(new \DateTime())
            ->setTotalAmount('100')
            ->setPaymentStatus('Pending')
            ->setReservationStatus('Pending')
            ->setDateReserved(new \DateTime());

        $this->assertInstanceOf(Reservation::class, $result);
    }
}
