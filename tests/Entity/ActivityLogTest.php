<?php

namespace App\Tests\Entity;

use App\Entity\ActivityLog;
use PHPUnit\Framework\TestCase;

class ActivityLogTest extends TestCase
{
    private ActivityLog $log;

    protected function setUp(): void
    {
        $this->log = new ActivityLog();
    }

    public function testNewActivityLogHasNullId(): void
    {
        $this->assertNull($this->log->getId());
    }

    public function testSetUserId(): void
    {
        $this->log->setUserId(42);
        $this->assertSame(42, $this->log->getUserId());
    }

    public function testSetUsername(): void
    {
        $this->log->setUsername('admin');
        $this->assertSame('admin', $this->log->getUsername());
    }

    public function testSetRole(): void
    {
        $this->log->setRole('ROLE_ADMIN');
        $this->assertSame('ROLE_ADMIN', $this->log->getRole());
    }

    public function testSetAction(): void
    {
        $this->log->setAction('CREATE');
        $this->assertSame('CREATE', $this->log->getAction());
    }

    public function testSetTargetData(): void
    {
        $this->log->setTargetData('Flower: Red Rose (ID: 1)');
        $this->assertSame('Flower: Red Rose (ID: 1)', $this->log->getTargetData());
    }

    public function testSetNullTargetData(): void
    {
        $this->log->setTargetData(null);
        $this->assertNull($this->log->getTargetData());
    }

    public function testSetCreatedAt(): void
    {
        $date = new \DateTime('2025-01-01');
        $this->log->setCreatedAt($date);
        $this->assertSame($date, $this->log->getCreatedAt());
    }

    public function testDefaultCreatedAtIsSetOnConstruction(): void
    {
        $log = new ActivityLog();
        $this->assertInstanceOf(\DateTimeInterface::class, $log->getCreatedAt());

        // Should be within last few seconds
        $diff = (new \DateTime())->getTimestamp() - $log->getCreatedAt()->getTimestamp();
        $this->assertLessThan(5, $diff);
    }

    public function testFluentInterface(): void
    {
        $result = $this->log
            ->setUserId(1)
            ->setUsername('testuser')
            ->setRole('ROLE_STAFF')
            ->setAction('LOGIN')
            ->setTargetData(null);

        $this->assertInstanceOf(ActivityLog::class, $result);
    }
}
