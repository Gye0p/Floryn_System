<?php

namespace App\Tests\Entity;

use App\Entity\NotificationLog;
use App\Entity\Customer;
use App\Entity\Reservation;
use PHPUnit\Framework\TestCase;

class NotificationLogTest extends TestCase
{
    private NotificationLog $log;

    protected function setUp(): void
    {
        $this->log = new NotificationLog();
    }

    public function testNewLogHasNullId(): void
    {
        $this->assertNull($this->log->getId());
    }

    public function testSetMessage(): void
    {
        $this->log->setMessage('Your reservation has been confirmed.');
        $this->assertSame('Your reservation has been confirmed.', $this->log->getMessage());
    }

    public function testSetChannel(): void
    {
        $this->log->setChannel('email');
        $this->assertSame('email', $this->log->getChannel());
    }

    public function testSetDateSent(): void
    {
        $date = new \DateTime('2025-06-10 14:30:00');
        $this->log->setDateSent($date);
        $this->assertSame($date, $this->log->getDateSent());
    }

    public function testSetStatus(): void
    {
        $this->log->setStatus('Sent');
        $this->assertSame('Sent', $this->log->getStatus());
    }

    public function testSetCustomer(): void
    {
        $customer = new Customer();
        $this->log->setCustomer($customer);
        $this->assertSame($customer, $this->log->getCustomer());
    }

    public function testSetReservation(): void
    {
        $reservation = new Reservation();
        $this->log->setReservation($reservation);
        $this->assertSame($reservation, $this->log->getReservation());
    }

    public function testSetNullReservation(): void
    {
        $this->log->setReservation(null);
        $this->assertNull($this->log->getReservation());
    }

    public function testFluentInterface(): void
    {
        $result = $this->log
            ->setMessage('Test message')
            ->setChannel('sms')
            ->setDateSent(new \DateTime())
            ->setStatus('Pending');

        $this->assertInstanceOf(NotificationLog::class, $result);
    }
}
