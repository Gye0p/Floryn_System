<?php

namespace App\Command;

use App\Repository\FlowerRepository;
use App\Service\EmailNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-low-stock',
    description: 'Check for low-stock flowers and send alert emails. Run this via cron or scheduler.',
)]
class CheckLowStockCommand extends Command
{
    public function __construct(
        private FlowerRepository $flowerRepository,
        private EmailNotificationService $emailNotificationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('threshold', 't', InputOption::VALUE_OPTIONAL, 'Stock threshold to trigger alert', 5)
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Admin email to receive alerts', 'admin@floryngarden.com');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $threshold = (int) $input->getOption('threshold');
        $adminEmail = $input->getOption('email');

        $io->title('Checking for Low Stock Flowers');

        $lowStockFlowers = $this->flowerRepository->createQueryBuilder('f')
            ->where('f.stockQuantity < :threshold')
            ->andWhere('f.status = :status')
            ->setParameter('threshold', $threshold)
            ->setParameter('status', 'Available')
            ->orderBy('f.stockQuantity', 'ASC')
            ->getQuery()
            ->getResult();

        if (empty($lowStockFlowers)) {
            $io->success('All flowers are sufficiently stocked.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($lowStockFlowers as $flower) {
            $rows[] = [
                $flower->getId(),
                $flower->getName(),
                $flower->getCategory(),
                $flower->getStockQuantity(),
                $flower->getSupplier()?->getSupplierName() ?? 'N/A',
            ];

            try {
                $this->emailNotificationService->sendLowStockAlert($flower, $adminEmail);
            } catch (\Exception $e) {
                $io->warning(sprintf('Failed to send alert for %s: %s', $flower->getName(), $e->getMessage()));
            }
        }

        $io->table(['ID', 'Name', 'Category', 'Stock', 'Supplier'], $rows);
        $io->warning(sprintf('%d flower(s) are below the stock threshold of %d.', count($lowStockFlowers), $threshold));

        return Command::SUCCESS;
    }
}
