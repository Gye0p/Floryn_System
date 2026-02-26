<?php

namespace App\Repository;

use App\Entity\FlowerBatch;
use App\Entity\Flower;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FlowerBatch>
 */
class FlowerBatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FlowerBatch::class);
    }

    /**
     * Get all active (non-expired, has stock) batches for a flower, ordered FIFO (oldest first).
     *
     * @return FlowerBatch[]
     */
    public function findActiveBatchesFifo(Flower $flower): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.flower = :flower')
            ->andWhere('b.active = true')
            ->andWhere('b.quantityRemaining > 0')
            ->setParameter('flower', $flower)
            ->orderBy('b.expiryDate', 'ASC') // Sell soonest-expiring first (FEFO)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all batches for a flower (including depleted/expired ones), newest first.
     *
     * @return FlowerBatch[]
     */
    public function findAllBatchesForFlower(Flower $flower): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.flower = :flower')
            ->setParameter('flower', $flower)
            ->orderBy('b.dateReceived', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the total remaining stock across all active batches for a flower.
     */
    public function getTotalRemainingStock(Flower $flower): int
    {
        $result = $this->createQueryBuilder('b')
            ->select('COALESCE(SUM(b.quantityRemaining), 0) as total')
            ->where('b.flower = :flower')
            ->andWhere('b.active = true')
            ->setParameter('flower', $flower)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * Get the earliest expiry date among active batches for a flower.
     */
    public function getEarliestExpiryDate(Flower $flower): ?\DateTime
    {
        $result = $this->createQueryBuilder('b')
            ->select('MIN(b.expiryDate) as earliest')
            ->where('b.flower = :flower')
            ->andWhere('b.active = true')
            ->andWhere('b.quantityRemaining > 0')
            ->setParameter('flower', $flower)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? new \DateTime($result) : null;
    }

    /**
     * Get batches expiring within N days (for freshness alerts).
     *
     * @return FlowerBatch[]
     */
    public function findExpiringBatches(int $withinDays = 3): array
    {
        $deadline = new \DateTime("+{$withinDays} days");

        return $this->createQueryBuilder('b')
            ->where('b.active = true')
            ->andWhere('b.quantityRemaining > 0')
            ->andWhere('b.expiryDate <= :deadline')
            ->setParameter('deadline', $deadline)
            ->orderBy('b.expiryDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Deduct quantity from a flower's batches using FEFO (First Expiry, First Out).
     * Uses pessimistic write lock to prevent race conditions when two staff
     * sell flowers simultaneously.
     *
     * @return int Amount actually deducted
     */
    public function deductStock(Flower $flower, int $quantity): int
    {
        $em = $this->getEntityManager();

        // Acquire pessimistic write lock on the flower row to serialize concurrent access
        $em->lock($flower, LockMode::PESSIMISTIC_WRITE);

        // Re-fetch batches after lock to get current state
        $batches = $this->findActiveBatchesFifo($flower);
        $remaining = $quantity;
        $totalDeducted = 0;

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;
            $em->lock($batch, LockMode::PESSIMISTIC_WRITE);
            $em->refresh($batch); // Get latest DB state
            $deducted = $batch->deduct($remaining);
            $remaining -= $deducted;
            $totalDeducted += $deducted;
        }

        // Sync flower's total stock from batches
        $flower->syncFromBatches();

        return $totalDeducted;
    }

    /**
     * Restore quantity back to the most recent batch (for cancellations).
     * Uses pessimistic write lock for concurrency safety.
     */
    public function restoreStock(Flower $flower, int $quantity): void
    {
        $em = $this->getEntityManager();
        $em->lock($flower, LockMode::PESSIMISTIC_WRITE);

        $batches = $this->findAllBatchesForFlower($flower);

        foreach ($batches as $batch) {
            if ($batch->getQuantityRemaining() < $batch->getQuantityReceived()) {
                $em->lock($batch, LockMode::PESSIMISTIC_WRITE);
                $em->refresh($batch);
                $canRestore = min($quantity, $batch->getQuantityReceived() - $batch->getQuantityRemaining());
                $batch->setQuantityRemaining($batch->getQuantityRemaining() + $canRestore);
                $batch->setActive(true);
                $quantity -= $canRestore;
                if ($quantity <= 0) break;
            }
        }

        $flower->syncFromBatches();
    }
}
