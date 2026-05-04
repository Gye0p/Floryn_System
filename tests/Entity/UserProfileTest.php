<?php

namespace App\Tests\Entity;

use App\Entity\User;
use App\Entity\Reservation;
use App\Entity\NotificationLog;
use PHPUnit\Framework\TestCase;

class UserProfileTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
    }

    public function testNewUserHasNullId(): void
    {
        $this->assertNull($this->user->getId());
    }

    public function testSetFullName(): void
    {
        $this->user->setFullName('Juan Dela Cruz');
        $this->assertSame('Juan Dela Cruz', $this->user->getFullName());
    }

    public function testSetPhone(): void
    {
        $this->user->setPhone('+639123456789');
        $this->assertSame('+639123456789', $this->user->getPhone());
    }

    public function testSetEmail(): void
    {
        $this->user->setEmail('juan@example.com');
        $this->assertSame('juan@example.com', $this->user->getEmail());
    }

    public function testSetAddress(): void
    {
        $this->user->setAddress('123 Main St');
        $this->assertSame('123 Main St', $this->user->getAddress());
    }

    public function testDateRegisteredAliasReturnsCreatedAt(): void
    {
        $date = new \DateTime();
        $this->user->setCreatedAt($date);
        $this->assertSame($date, $this->user->getDateRegistered());
    }

    public function testIsCustomerReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->user->isCustomer());
    }

    public function testIsCustomerReturnsTrueWithRole(): void
    {
        $this->user->setRoles(['ROLE_CUSTOMER']);
        $this->assertTrue($this->user->isCustomer());
    }

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $this->user->setRoles([]);
        $this->assertContains('ROLE_USER', $this->user->getRoles());
    }

    public function testGetRolesDoesNotAutoAddRoleStaff(): void
    {
        $this->user->setRoles(['ROLE_CUSTOMER']);
        $roles = $this->user->getRoles();
        $this->assertNotContains('ROLE_STAFF', $roles);
        $this->assertContains('ROLE_CUSTOMER', $roles);
        $this->assertContains('ROLE_USER', $roles);
    }

    public function testReservationsCollection(): void
    {
        $this->assertCount(0, $this->user->getReservations());
    }

    public function testAddReservation(): void
    {
        $reservation = new Reservation();
        $this->user->addReservation($reservation);

        $this->assertCount(1, $this->user->getReservations());
        $this->assertSame($this->user, $reservation->getCustomer());
    }

    public function testRemoveReservation(): void
    {
        $reservation = new Reservation();
        $this->user->addReservation($reservation);
        $this->user->removeReservation($reservation);

        $this->assertCount(0, $this->user->getReservations());
    }

    public function testNotificationLogsCollection(): void
    {
        $this->assertCount(0, $this->user->getNotificationLogs());
    }

    public function testAddNotificationLog(): void
    {
        $log = new NotificationLog();
        $this->user->addNotificationLog($log);

        $this->assertCount(1, $this->user->getNotificationLogs());
        $this->assertSame($this->user, $log->getCustomer());
    }

    public function testFluentInterface(): void
    {
        $result = $this->user
            ->setFullName('Test User')
            ->setPhone('+639123456789')
            ->setEmail('test@test.com')
            ->setAddress('123 St');

        $this->assertInstanceOf(User::class, $result);
    }
}
