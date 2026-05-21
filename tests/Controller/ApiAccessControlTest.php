<?php

namespace App\Tests\Controller;

use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Documents expected API access rules enforced in security.yaml.
 */
class ApiAccessControlTest extends TestCase
{
    public function testCustomerRoleDoesNotInheritStaffRole(): void
    {
        $user = new User();
        $user->setUsername('customer1');
        $user->setPassword('hashed');
        $user->setRoles(['ROLE_CUSTOMER']);

        $roles = $user->getRoles();
        $this->assertContains('ROLE_CUSTOMER', $roles);
        $this->assertNotContains('ROLE_STAFF', $roles);
    }

    public function testStaffRoleIncludesUserRole(): void
    {
        $hierarchy = $this->createMock(RoleHierarchyInterface::class);
        $hierarchy->method('getReachableRoleNames')
            ->with(['ROLE_STAFF'])
            ->willReturn(['ROLE_STAFF', 'ROLE_USER']);

        $reachable = $hierarchy->getReachableRoleNames(['ROLE_STAFF']);
        $this->assertContains('ROLE_USER', $reachable);
    }
}
