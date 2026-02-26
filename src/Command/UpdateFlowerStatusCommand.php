<?php

namespace App\Command;

use App\Service\FlowerStatusUpdater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-flower-status',
    description: 'Update flower freshness statuses based on expiry dates. Run this via cron or scheduler.',
)]
class UpdateFlowerStatusCommand extends Command
{
    public function __construct(
        private FlowerStatusUpdater $flowerStatusUpdater
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Updating Flower Freshness Statuses');

        $stats = $this->flowerStatusUpdater->updateFlowerStatuses();

        $io->table(
            ['Status', 'Count'],
            [
                ['Fresh', $stats['fresh']],
                ['Good', $stats['good']],
                ['Last Sale', $stats['lastSale']],
                ['Expired', $stats['expired']],
                ['Total', $stats['total']],
            ]
        );

        $io->success(sprintf(
            'Updated %d flowers: %d Fresh, %d Good, %d Last Sale, %d Expired',
            $stats['total'],
            $stats['fresh'],
            $stats['good'],
            $stats['lastSale'],
            $stats['expired']
        ));

        return Command::SUCCESS;
    }
}
