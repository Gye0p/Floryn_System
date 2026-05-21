<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\RefreshTokenService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class RefreshTokenServiceTest extends TestCase
{
    public function testFindValidRejectsMalformedToken(): void
    {
        $service = $this->createService();
        $this->assertNull($service->findValid('not-a-valid-token'));
        $this->assertNull($service->findValid(''));
    }

    private function createService(): RefreshTokenService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(\App\Repository\RefreshTokenRepository::class);
        $repo->method('deleteExpiredForUser')->willReturn(0);

        return new RefreshTokenService($em, $repo, 3600);
    }
}
