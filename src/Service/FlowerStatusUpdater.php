<?php

namespace App\Service;

use App\Entity\Flower;
use App\Entity\FlowerBatch;
use Doctrine\ORM\EntityManagerInterface;

class FlowerStatusUpdater
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Update all flower and batch statuses based on expiry dates.
     *
     * Batch-level logic:
     *  - Each batch gets its own freshnessStatus based on its expiryDate
     *  - Expired batches: quantityRemaining → 0, active → false
     *  - Last Sale batches: flower gets 20% discount (if any batch is Last Sale)
     *
     * Flower-level sync:
     *  - stockQuantity = sum of all active batch remaining quantities
     *  - expiryDate = earliest active batch expiry (drives freshness display)
     *  - freshnessStatus = worst freshness among active batches
     *  - status = Available / Sold Out / Unavailable based on remaining stock
     */
    public function updateFlowerStatuses(): array
    {
        $repository = $this->entityManager->getRepository(Flower::class);
        $flowers = $repository->findAll();
        $today = new \DateTime('today');
        $batchSize = 50;
        $i = 0;
        
        $stats = [
            'fresh' => 0,
            'good' => 0,
            'lastSale' => 0,
            'expired' => 0,
            'total' => count($flowers)
        ];

        foreach ($flowers as $flower) {
            $batches = $flower->getBatches();
            $hasBatches = !$batches->isEmpty();

            // ── Process individual batches ──
            if ($hasBatches) {
                $hasLastSaleBatch = false;

                foreach ($batches as $batch) {
                    /** @var FlowerBatch $batch */
                    if (!$batch->isActive() && $batch->getQuantityRemaining() <= 0) {
                        continue; // Already fully consumed
                    }

                    $expiryDate = $batch->getExpiryDate();
                    if (!$expiryDate) continue;

                    $interval = $today->diff($expiryDate);
                    $daysUntilExpiry = $interval->invert ? -$interval->days : $interval->days;

                    if ($daysUntilExpiry < 0) {
                        // Expired batch → zero out and deactivate
                        $batch->setFreshnessStatus('Expired');
                        $batch->setQuantityRemaining(0);
                        $batch->setActive(false);
                    } elseif ($daysUntilExpiry <= 3) {
                        $batch->setFreshnessStatus('Last Sale');
                        $hasLastSaleBatch = true;
                    } elseif ($daysUntilExpiry <= 7) {
                        $batch->setFreshnessStatus('Good');
                    } else {
                        $batch->setFreshnessStatus('Fresh');
                    }
                }

                // Sync flower summary from its batches
                $flower->syncFromBatches();

                // Apply discount if any active batch is Last Sale
                if ($hasLastSaleBatch && $flower->getStockQuantity() > 0) {
                    $flower->setDiscountPrice($flower->getPrice() * 0.8);
                } elseif ($flower->getStockQuantity() > 0) {
                    $flower->setDiscountPrice(null);
                }
            }

            // ── Determine flower-level freshness & status ──
            if (!$flower->getExpiryDate()) {
                if (++$i % $batchSize === 0) $this->entityManager->flush();
                continue;
            }

            // Skip flowers that are already sold out (stock = 0) — don't overwrite their status
            if ($flower->getStockQuantity() <= 0 && $flower->getStatus() === 'Sold Out') {
                $flower->setDiscountPrice(null);
                $stats['expired']++;
                if (++$i % $batchSize === 0) $this->entityManager->flush();
                continue;
            }

            $expiryDate = $flower->getExpiryDate();
            $interval = $today->diff($expiryDate);
            $daysUntilExpiry = $interval->invert ? -$interval->days : $interval->days;

            if ($daysUntilExpiry < 0) {
                // Expired — zero out stock, mark unavailable, record soldAt
                $flower->setFreshnessStatus('Expired');
                $flower->setStatus('Unavailable');
                $flower->setStockQuantity(0);
                $flower->setDiscountPrice(null);
                if (!$flower->getSoldAt()) {
                    $flower->setSoldAt(new \DateTime());
                }
                $stats['expired']++;
            } elseif ($flower->getStockQuantity() <= 0) {
                // Stock depleted but not yet marked — mark as Sold Out
                $flower->setStatus('Sold Out');
                $flower->setFreshnessStatus('Expired');
                $flower->setDiscountPrice(null);
                $stats['expired']++;
            } elseif ($daysUntilExpiry <= 3) {
                $flower->setFreshnessStatus('Last Sale');
                $flower->setStatus('Available');
                $flower->setDiscountPrice($flower->getPrice() * 0.8);
                $stats['lastSale']++;
            } elseif ($daysUntilExpiry <= 7) {
                $flower->setFreshnessStatus('Good');
                $flower->setStatus('Available');
                if (!$hasBatches) $flower->setDiscountPrice(null);
                $stats['good']++;
            } else {
                $flower->setFreshnessStatus('Fresh');
                $flower->setStatus('Available');
                if (!$hasBatches) $flower->setDiscountPrice(null);
                $stats['fresh']++;
            }

            if (++$i % $batchSize === 0) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();
        
        return $stats;
    }

    /**
     * Read-only freshness stats (no DB writes)
     * Only counts flowers that are actively in stock (excludes Sold Out and Unavailable)
     */
    public function getFreshnessStats(): array
    {
        $repository = $this->entityManager->getRepository(Flower::class);
        $flowers = $repository->findAll();

        // Filter out sold/unavailable/out-of-stock flowers
        $activeFlowers = array_filter($flowers, fn(Flower $f) => 
            $f->getStockQuantity() > 0 && !in_array($f->getStatus(), ['Sold Out', 'Unavailable'])
        );

        $stats = [
            'fresh' => 0,
            'good' => 0,
            'lastSale' => 0,
            'expired' => 0,
            'total' => count($activeFlowers)
        ];

        foreach ($activeFlowers as $flower) {
            match ($flower->getFreshnessStatus()) {
                'Fresh' => $stats['fresh']++,
                'Good' => $stats['good']++,
                'Last Sale' => $stats['lastSale']++,
                'Expired' => $stats['expired']++,
                default => null,
            };
        }

        return $stats;
    }

    /**
     * Get flowers that are expiring soon (within 3 days)
     * Excludes sold out and unavailable flowers
     */
    public function getExpiringSoonFlowers(): array
    {
        $repository = $this->entityManager->getRepository(Flower::class);
        
        return $repository->createQueryBuilder('f')
            ->where('f.freshnessStatus = :lastSale')
            ->andWhere('f.status != :soldOut')
            ->andWhere('f.status != :unavailable')
            ->andWhere('f.stockQuantity > 0')
            ->setParameter('lastSale', 'Last Sale')
            ->setParameter('soldOut', 'Sold Out')
            ->setParameter('unavailable', 'Unavailable')
            ->orderBy('f.expiryDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get freshness distribution for dashboard charts
     * Excludes sold out and unavailable flowers
     */
    public function getFreshnessDistribution(): array
    {
        $repository = $this->entityManager->getRepository(Flower::class);
        
        $result = $repository->createQueryBuilder('f')
            ->select('f.freshnessStatus, COUNT(f.id) as count')
            ->where('f.status != :soldOut')
            ->andWhere('f.status != :unavailable')
            ->andWhere('f.stockQuantity > 0')
            ->setParameter('soldOut', 'Sold Out')
            ->setParameter('unavailable', 'Unavailable')
            ->groupBy('f.freshnessStatus')
            ->getQuery()
            ->getResult();

        $distribution = [
            'Fresh' => 0,
            'Good' => 0,
            'Last Sale' => 0,
            'Expired' => 0
        ];

        foreach ($result as $row) {
            if (isset($distribution[$row['freshnessStatus']])) {
                $distribution[$row['freshnessStatus']] = (int) $row['count'];
            }
        }

        return $distribution;
    }

    /**
     * Get recently expired flowers for notifications
     */
    public function getRecentlyExpiredFlowers(): array
    {
        $repository = $this->entityManager->getRepository(Flower::class);
        $yesterday = new \DateTime('-1 day');
        
        return $repository->createQueryBuilder('f')
            ->where('f.freshnessStatus = :expired')
            ->andWhere('f.expiryDate >= :yesterday')
            ->setParameter('expired', 'Expired')
            ->setParameter('yesterday', $yesterday)
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate total potential savings from discounted flowers
     * Excludes sold out and unavailable flowers
     */
    public function getTotalSavings(): array
    {
        $repository = $this->entityManager->getRepository(Flower::class);
        
        $discountedFlowers = $repository->createQueryBuilder('f')
            ->where('f.freshnessStatus = :lastSale')
            ->andWhere('f.discountPrice IS NOT NULL')
            ->andWhere('f.status != :soldOut')
            ->andWhere('f.status != :unavailable')
            ->andWhere('f.stockQuantity > 0')
            ->setParameter('lastSale', 'Last Sale')
            ->setParameter('soldOut', 'Sold Out')
            ->setParameter('unavailable', 'Unavailable')
            ->getQuery()
            ->getResult();

        $totalSavings = 0;
        $totalDiscountedValue = 0;
        $totalOriginalValue = 0;

        foreach ($discountedFlowers as $flower) {
            $originalPrice = $flower->getPrice() * $flower->getStockQuantity();
            $discountedPrice = $flower->getDiscountPrice() * $flower->getStockQuantity();
            
            $totalOriginalValue += $originalPrice;
            $totalDiscountedValue += $discountedPrice;
            $totalSavings += ($originalPrice - $discountedPrice);
        }

        return [
            'totalSavings' => $totalSavings,
            'totalDiscountedValue' => $totalDiscountedValue,
            'totalOriginalValue' => $totalOriginalValue,
            'discountedItemsCount' => count($discountedFlowers)
        ];
    }

    /**
     * Get freshness distribution by flower category
     * Returns array with category as key and freshness counts as values
     * Excludes sold out and unavailable flowers
     */
    public function getFreshnessByCategory(): array
    {
        $repository = $this->entityManager->getRepository(Flower::class);
        $flowers = $repository->findAll();

        $summary = [];

        foreach ($flowers as $flower) {
            // Skip sold out, unavailable, and out-of-stock flowers
            if ($flower->getStockQuantity() <= 0 || in_array($flower->getStatus(), ['Sold Out', 'Unavailable'])) {
                continue;
            }

            $category = $flower->getCategory();
            $status = $flower->getFreshnessStatus() ?: 'Unknown';

            if (!isset($summary[$category])) {
                $summary[$category] = [
                    'Fresh' => 0,
                    'Good' => 0,
                    'Last Sale' => 0,
                    'Expired' => 0,
                    'total' => 0
                ];
            }

            if (isset($summary[$category][$status])) {
                $summary[$category][$status]++;
            }
            $summary[$category]['total']++;
        }

        // Sort by category name
        ksort($summary);

        return $summary;
    }

    /**
     * Get freshness distribution by individual flower name  
     * Returns array with flower name as key and freshness counts as values
     * Excludes sold out and unavailable flowers
     */
    public function getFreshnessByFlowerName(): array
    {
        $repository = $this->entityManager->getRepository(Flower::class);
        $flowers = $repository->findAll();

        $summary = [];

        foreach ($flowers as $flower) {
            // Skip sold out, unavailable, and out-of-stock flowers
            if ($flower->getStockQuantity() <= 0 || in_array($flower->getStatus(), ['Sold Out', 'Unavailable'])) {
                continue;
            }

            $name = $flower->getName();
            $status = $flower->getFreshnessStatus() ?: 'Unknown';

            if (!isset($summary[$name])) {
                $summary[$name] = [
                    'Fresh' => 0,
                    'Good' => 0,
                    'Last Sale' => 0,
                    'Expired' => 0,
                    'total' => 0,
                    'stockQuantity' => 0,
                    'category' => $flower->getCategory()
                ];
            }

            if (isset($summary[$name][$status])) {
                $summary[$name][$status]++;
            }
            $summary[$name]['total']++;
            $summary[$name]['stockQuantity'] += $flower->getStockQuantity();
        }

        // Sort by flower name
        ksort($summary);

        return $summary;
    }
}