<?php

namespace App\Command;

use App\Entity\FlowerBatch;
use App\Repository\FlowerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-flower-batches',
    description: 'Create initial FlowerBatch records for existing flowers that have no batches yet.',
)]
class SeedFlowerBatchesCommand extends Command
{
    public function __construct(
        private FlowerRepository $flowerRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding FlowerBatch records for existing flowers');

        $flowers = $this->flowerRepository->findAll();
        $seeded = 0;
        $skipped = 0;

        foreach ($flowers as $flower) {
            // Skip flowers that already have batches
            if (!$flower->getBatches()->isEmpty()) {
                $skipped++;
                continue;
            }

            // Skip flowers with 0 stock and no expiry (truly empty entries)
            if ($flower->getStockQuantity() <= 0 && $flower->getExpiryDate() === null) {
                $io->note(sprintf('Skipping "%s" (ID %d): no stock and no expiry date', $flower->getName(), $flower->getId()));
                $skipped++;
                continue;
            }

            $batch = new FlowerBatch();
            $batch->setFlower($flower);
            $batch->setQuantityReceived($flower->getStockQuantity());
            $batch->setQuantityRemaining($flower->getStockQuantity());
            $batch->setDateReceived($flower->getDateReceived() ?? new \DateTime());
            $batch->setExpiryDate($flower->getExpiryDate());

            // If stock is 0, mark batch as inactive
            if ($flower->getStockQuantity() <= 0) {
                $batch->setActive(false);
            } else {
                $batch->setActive(true);
            }

            $this->entityManager->persist($batch);
            $seeded++;

            $io->text(sprintf(
                '  + "%s" (ID %d): batch with %d units, expires %s',
                $flower->getName(),
                $flower->getId(),
                $flower->getStockQuantity(),
                $flower->getExpiryDate() ? $flower->getExpiryDate()->format('Y-m-d') : 'N/A'
            ));
        }

        $this->entityManager->flush();

        $io->success(sprintf('Done! Seeded %d batch(es), skipped %d flower(s).', $seeded, $skipped));

        return Command::SUCCESS;
    }
}
