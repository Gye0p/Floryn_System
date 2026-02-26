<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testUserCreation(): void
    {
        $user = new User();
        $this->assertNull($user->getId());
        $this->assertNull($user->getUsername());
    }

    public function testSetUsername(): void
    {
        $user = new User();
        $user->setUsername('testuser');

        $this->assertSame('testuser', $user->getUsername());
        $this->assertSame('testuser', $user->getUserIdentifier());
    }

    public function testDefaultRolesIncludeStaff(): void
    {
        $user = new User();
        $roles = $user->getRoles();

        $this->assertContains('ROLE_STAFF', $roles);
    }

    public function testSetRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $roles = $user->getRoles();
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_STAFF', $roles); // default always present
    }

    public function testRolesAreUnique(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_STAFF', 'ROLE_STAFF', 'ROLE_ADMIN']);

        $roles = $user->getRoles();
        $this->assertCount(2, $roles); // ROLE_STAFF + ROLE_ADMIN
    }

    public function testSetPassword(): void
    {
        $user = new User();
        $user->setPassword('hashed_password');

        $this->assertSame('hashed_password', $user->getPassword());
    }

    public function testFluentInterface(): void
    {
        $user = new User();
        $result = $user->setUsername('test')->setRoles(['ROLE_ADMIN'])->setPassword('pass');

        $this->assertInstanceOf(User::class, $result);
    }
}
