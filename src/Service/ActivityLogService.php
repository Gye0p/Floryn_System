<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ActivityLogService
{
    private EntityManagerInterface $entityManager;
    private Security $security;

    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    /**
     * Log an activity action
     */
    public function log(string $action, ?string $targetData = null): void
    {
        $user = $this->security->getUser();
        
        if (!$user instanceof User) {
            return; // Don't log if no user is authenticated
        }

        $log = new ActivityLog();
        $log->setUserId($user->getId());
        $log->setUsername($user->getUserIdentifier());
        $log->setRole(implode(', ', $user->getRoles()));
        $log->setAction($action);
        $log->setTargetData($targetData);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    /**
     * Log login action
     */
    public function logLogin(): void
    {
        $this->log('LOGIN');
    }

    /**
     * Log logout action
     */
    public function logLogout(): void
    {
        $this->log('LOGOUT');
    }

    /**
     * Log create action
     */
    public function logCreate(string $entityType, int $entityId, string $entityName = ''): void
    {
        $targetData = sprintf('%s: %s (ID: %d)', $entityType, $entityName, $entityId);
        $this->log('CREATE', $targetData);
    }

    /**
     * Log update action
     */
    public function logUpdate(string $entityType, int $entityId, string $entityName = ''): void
    {
        $targetData = sprintf('%s: %s (ID: %d)', $entityType, $entityName, $entityId);
        $this->log('UPDATE', $targetData);
    }

    /**
     * Log delete action
     */
    public function logDelete(string $entityType, int $entityId, string $entityName = ''): void
    {
        $targetData = sprintf('%s: %s (ID: %d)', $entityType, $entityName, $entityId);
        $this->log('DELETE', $targetData);
    }
}
