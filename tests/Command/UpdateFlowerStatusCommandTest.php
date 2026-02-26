<?php

namespace App\Tests\Command;

use App\Command\UpdateFlowerStatusCommand;
use App\Service\FlowerStatusUpdater;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class UpdateFlowerStatusCommandTest extends TestCase
{
    public function testExecuteDisplaysStats(): void
    {
        $updater = $this->createMock(FlowerStatusUpdater::class);
        $updater->method('updateFlowerStatuses')->willReturn([
            'fresh' => 10,
            'good' => 5,
            'lastSale' => 3,
            'expired' => 2,
            'total' => 20,
        ]);

        $command = new UpdateFlowerStatusCommand($updater);

        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();

        $this->assertStringContainsString('Fresh', $output);
        $this->assertStringContainsString('Expired', $output);
        $this->assertStringContainsString('Updated 20 flowers', $output);
    }

    public function testExecuteCallsFlowerStatusUpdater(): void
    {
        $updater = $this->createMock(FlowerStatusUpdater::class);
        $updater->expects($this->once())
            ->method('updateFlowerStatuses')
            ->willReturn([
                'fresh' => 0,
                'good' => 0,
                'lastSale' => 0,
                'expired' => 0,
                'total' => 0,
            ]);

        $command = new UpdateFlowerStatusCommand($updater);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
    }
}
