<?php

namespace App\Tests\Entity;

use App\Entity\Customer;
use App\Entity\Reservation;
use App\Entity\NotificationLog;
use PHPUnit\Framework\TestCase;

class CustomerTest extends TestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        $this->customer = new Customer();
    }

    public function testNewCustomerHasNullId(): void
    {
        $this->assertNull($this->customer->getId());
    }

    public function testSetFullName(): void
    {
        $this->customer->setFullName('Juan Dela Cruz');
        $this->assertSame('Juan Dela Cruz', $this->customer->getFullName());
    }

    public function testSetPhone(): void
    {
        $this->customer->setPhone('+639123456789');
        $this->assertSame('+639123456789', $this->customer->getPhone());
    }

    public function testSetEmail(): void
    {
        $this->customer->setEmail('juan@example.com');
        $this->assertSame('juan@example.com', $this->customer->getEmail());
    }

    public function testSetAddress(): void
    {
        $this->customer->setAddress('123 Main St');
        $this->assertSame('123 Main St', $this->customer->getAddress());
    }

    public function testSetDateRegistered(): void
    {
        $date = new \DateTime();
        $this->customer->setDateRegistered($date);
        $this->assertSame($date, $this->customer->getDateRegistered());
    }

    public function testReservationsCollection(): void
    {
        $this->assertCount(0, $this->customer->getReservations());
    }

    public function testAddReservation(): void
    {
        $reservation = new Reservation();
        $this->customer->addReservation($reservation);

        $this->assertCount(1, $this->customer->getReservations());
        $this->assertSame($this->customer, $reservation->getCustomer());
    }

    public function testRemoveReservation(): void
    {
        $reservation = new Reservation();
        $this->customer->addReservation($reservation);
        $this->customer->removeReservation($reservation);

        $this->assertCount(0, $this->customer->getReservations());
    }

    public function testNotificationLogsCollection(): void
    {
        $this->assertCount(0, $this->customer->getNotificationLogs());
    }

    public function testAddNotificationLog(): void
    {
        $log = new NotificationLog();
        $this->customer->addNotificationLog($log);

        $this->assertCount(1, $this->customer->getNotificationLogs());
        $this->assertSame($this->customer, $log->getCustomer());
    }

    public function testFluentInterface(): void
    {
        $result = $this->customer
            ->setFullName('Test User')
            ->setPhone('+639123456789')
            ->setEmail('test@test.com')
            ->setAddress('123 St');

        $this->assertInstanceOf(Customer::class, $result);
    }
}
