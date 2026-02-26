<?php

namespace App\Tests\Service;

use App\Entity\Flower;
use App\Service\FlowerStatusUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FlowerStatusUpdaterTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private EntityRepository&MockObject $repository;
    private FlowerStatusUpdater $updater;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->with(Flower::class)
            ->willReturn($this->repository);

        $this->updater = new FlowerStatusUpdater($this->entityManager);
    }

    public function testUpdateFlowerStatusesSetsExpiredForPastExpiry(): void
    {
        $flower = new Flower();
        $flower->setPrice('100.00');
        $flower->setExpiryDate(new \DateTime('-1 day'));

        $this->repository->method('findAll')->willReturn([$flower]);
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $stats = $this->updater->updateFlowerStatuses();

        $this->assertSame('Expired', $flower->getFreshnessStatus());
        $this->assertSame('Unavailable', $flower->getStatus());
        $this->assertNull($flower->getDiscountPrice());
        $this->assertSame(1, $stats['expired']);
    }

    public function testUpdateFlowerStatusesSetsLastSaleForExpiringSoon(): void
    {
        $flower = new Flower();
        $flower->setPrice('100.00');
        $flower->setExpiryDate(new \DateTime('+2 days'));

        $this->repository->method('findAll')->willReturn([$flower]);
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $stats = $this->updater->updateFlowerStatuses();

        $this->assertSame('Last Sale', $flower->getFreshnessStatus());
        $this->assertSame('Available', $flower->getStatus());
        // 20% discount: 100 * 0.8 = 80
        $this->assertEquals(80, $flower->getDiscountPrice());
        $this->assertSame(1, $stats['lastSale']);
    }

    public function testUpdateFlowerStatusesSetsGoodFor4To7Days(): void
    {
        $flower = new Flower();
        $flower->setPrice('100.00');
        $flower->setExpiryDate(new \DateTime('+5 days'));

        $this->repository->method('findAll')->willReturn([$flower]);
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $stats = $this->updater->updateFlowerStatuses();

        $this->assertSame('Good', $flower->getFreshnessStatus());
        $this->assertSame('Available', $flower->getStatus());
        $this->assertNull($flower->getDiscountPrice());
        $this->assertSame(1, $stats['good']);
    }

    public function testUpdateFlowerStatusesSetsFreshFor8PlusDays(): void
    {
        $flower = new Flower();
        $flower->setPrice('100.00');
        $flower->setExpiryDate(new \DateTime('+10 days'));

        $this->repository->method('findAll')->willReturn([$flower]);
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $stats = $this->updater->updateFlowerStatuses();

        $this->assertSame('Fresh', $flower->getFreshnessStatus());
        $this->assertSame('Available', $flower->getStatus());
        $this->assertNull($flower->getDiscountPrice());
        $this->assertSame(1, $stats['fresh']);
    }

    public function testUpdateFlowerStatusesSkipsFlowersWithoutExpiryDate(): void
    {
        $flower = new Flower();
        $flower->setPrice('100.00');
        // No expiry date set

        $this->repository->method('findAll')->willReturn([$flower]);
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $stats = $this->updater->updateFlowerStatuses();

        $this->assertSame(1, $stats['total']);
        $this->assertSame(0, $stats['fresh']);
        $this->assertSame(0, $stats['good']);
        $this->assertSame(0, $stats['lastSale']);
        $this->assertSame(0, $stats['expired']);
    }

    public function testUpdateFlowerStatusesMultipleFlowers(): void
    {
        $fresh = new Flower();
        $fresh->setPrice('50.00');
        $fresh->setExpiryDate(new \DateTime('+15 days'));

        $expired = new Flower();
        $expired->setPrice('30.00');
        $expired->setExpiryDate(new \DateTime('-3 days'));

        $lastSale = new Flower();
        $lastSale->setPrice('200.00');
        $lastSale->setExpiryDate(new \DateTime('+1 day'));

        $this->repository->method('findAll')->willReturn([$fresh, $expired, $lastSale]);
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $stats = $this->updater->updateFlowerStatuses();

        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['fresh']);
        $this->assertSame(0, $stats['good']);
        $this->assertSame(1, $stats['lastSale']);
        $this->assertSame(1, $stats['expired']);
    }

    public function testGetFreshnessStatsReadOnly(): void
    {
        $fresh = new Flower();
        $fresh->setFreshnessStatus('Fresh');

        $good = new Flower();
        $good->setFreshnessStatus('Good');

        $lastSale = new Flower();
        $lastSale->setFreshnessStatus('Last Sale');

        $expired = new Flower();
        $expired->setFreshnessStatus('Expired');

        $this->repository->method('findAll')->willReturn([$fresh, $good, $lastSale, $expired]);

        // flush should NOT be called (read-only)
        $this->entityManager->expects($this->never())->method('flush');

        $stats = $this->updater->getFreshnessStats();

        $this->assertSame(4, $stats['total']);
        $this->assertSame(1, $stats['fresh']);
        $this->assertSame(1, $stats['good']);
        $this->assertSame(1, $stats['lastSale']);
        $this->assertSame(1, $stats['expired']);
    }

    public function testGetFreshnessStatsWithUnknownStatus(): void
    {
        $flower = new Flower();
        $flower->setFreshnessStatus('Unknown');

        $this->repository->method('findAll')->willReturn([$flower]);

        $stats = $this->updater->getFreshnessStats();

        $this->assertSame(1, $stats['total']);
        $this->assertSame(0, $stats['fresh']);
        $this->assertSame(0, $stats['good']);
        $this->assertSame(0, $stats['lastSale']);
        $this->assertSame(0, $stats['expired']);
    }

    public function testGetFreshnessStatsEmptyRepository(): void
    {
        $this->repository->method('findAll')->willReturn([]);

        $stats = $this->updater->getFreshnessStats();

        $this->assertSame(0, $stats['total']);
        $this->assertSame(0, $stats['fresh']);
    }
}
