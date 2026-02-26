<?php

namespace App\Tests\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class ActivityLogServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private Security&MockObject $security;
    private ActivityLogService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->service = new ActivityLogService($this->entityManager, $this->security);
    }

    private function createMockUser(int $id = 1, string $username = 'admin', array $roles = ['ROLE_ADMIN']): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getUserIdentifier')->willReturn($username);
        $user->method('getRoles')->willReturn($roles);
        return $user;
    }

    public function testLogPersistsActivityLog(): void
    {
        $user = $this->createMockUser();
        $this->security->method('getUser')->willReturn($user);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($log) {
                return $log instanceof ActivityLog
                    && $log->getAction() === 'CREATE'
                    && $log->getTargetData() === 'Test data'
                    && $log->getUserId() === 1
                    && $log->getUsername() === 'admin';
            }));
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->log('CREATE', 'Test data');
    }

    public function testLogDoesNothingWhenNoUserAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $this->service->log('CREATE', 'Test data');
    }

    public function testLogLogin(): void
    {
        $user = $this->createMockUser();
        $this->security->method('getUser')->willReturn($user);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(fn($log) => $log instanceof ActivityLog && $log->getAction() === 'LOGIN'));
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->logLogin();
    }

    public function testLogLogout(): void
    {
        $user = $this->createMockUser();
        $this->security->method('getUser')->willReturn($user);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(fn($log) => $log instanceof ActivityLog && $log->getAction() === 'LOGOUT'));
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->logLogout();
    }

    public function testLogCreate(): void
    {
        $user = $this->createMockUser();
        $this->security->method('getUser')->willReturn($user);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($log) {
                return $log instanceof ActivityLog
                    && $log->getAction() === 'CREATE'
                    && $log->getTargetData() === 'Flower: Red Rose (ID: 5)';
            }));

        $this->service->logCreate('Flower', 5, 'Red Rose');
    }

    public function testLogUpdate(): void
    {
        $user = $this->createMockUser();
        $this->security->method('getUser')->willReturn($user);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($log) {
                return $log instanceof ActivityLog
                    && $log->getAction() === 'UPDATE'
                    && $log->getTargetData() === 'Customer: Juan (ID: 3)';
            }));

        $this->service->logUpdate('Customer', 3, 'Juan');
    }

    public function testLogDelete(): void
    {
        $user = $this->createMockUser();
        $this->security->method('getUser')->willReturn($user);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($log) {
                return $log instanceof ActivityLog
                    && $log->getAction() === 'DELETE'
                    && $log->getTargetData() === 'Supplier: Flora Co (ID: 7)';
            }));

        $this->service->logDelete('Supplier', 7, 'Flora Co');
    }

    public function testLogSetsRoleFromUser(): void
    {
        $user = $this->createMockUser(1, 'staff_user', ['ROLE_STAFF', 'ROLE_USER']);
        $this->security->method('getUser')->willReturn($user);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($log) {
                return $log instanceof ActivityLog
                    && $log->getRole() === 'ROLE_STAFF, ROLE_USER';
            }));

        $this->service->log('TEST');
    }
}
