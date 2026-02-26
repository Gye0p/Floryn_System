<?php

namespace App\Service;

use App\Entity\Flower;
use Doctrine\ORM\EntityManagerInterface;

class FlowerStatusUpdater
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Update all flower statuses based on expiry dates
     * - Fresh: More than 7 days until expiry
     * - Good: 3-7 days until expiry  
     * - Last Sale: 0-3 days until expiry (with 20% discount)
     * - Expired: Past expiry date (marked as Unavailable)
     */
    public function updateFlowerStatuses(): array
    {
        $repository = $this->entityManager->getRepository(Flower::class);
        $flowers = $repository->findAll();
        $today = new \DateTime();
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
            if (!$flower->getExpiryDate()) {
                continue; // Skip flowers without expiry date
            }

            $expiryDate = $flower->getExpiryDate();
            $interval = $today->diff($expiryDate);
            $daysUntilExpiry = $interval->invert ? -$interval->days : $interval->days;

            if ($daysUntilExpiry < 0) {
                // Expired
                $flower->setFreshnessStatus('Expired');
                $flower->setStatus('Unavailable');
                $flower->setDiscountPrice(null);
                $stats['expired']++;
            } elseif ($daysUntilExpiry <= 3) {
                // Last Sale (0-3 days) - Apply 20% discount
                $flower->setFreshnessStatus('Last Sale');
                $flower->setStatus('Available');
                $discountPrice = $flower->getPrice() * 0.8; // 20% off
                $flower->setDiscountPrice($discountPrice);
                $stats['lastSale']++;
            } elseif ($daysUntilExpiry <= 7) {
                // Good (4-7 days)
                $flower->setFreshnessStatus('Good');
                $flower->setStatus('Available');
                $flower->setDiscountPrice(null);
                $stats['good']++;
            } else {
                // Fresh (8+ days)
                $flower->setFreshnessStatus('Fresh');
                $flower->setStatus('Available');
                $flower->setDiscountPrice(null);
                $stats['fresh']++;
            }

            // Flush in batches to reduce memory usage
            if (++$i % $batchSize === 0) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();
        
        return $stats;
    }

    /**
     * Read-only freshness stats (no DB writes)
     */
    public function getFreshnessStats(): array
    {
        $repository = $this->entityManager->getRepository(Flower::class);
        $flowers = $repository->findAll();
        $today = new \DateTime();

        $stats = [
            'fresh' => 0,
            'good' => 0,
            'lastSale' => 0,
            'expired' => 0,
            'total' => count($flowers)
        ];

        foreach ($flowers as $flower) {
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
     */
    public function getExpiringSoonFlowers(): array
    {
        $repository = $this->entityManager->getRepository(Flower::class);
        
        return $repository->createQueryBuilder('f')
            ->where('f.freshnessStatus = :lastSale')
            ->setParameter('lastSale', 'Last Sale')
            ->orderBy('f.expiryDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get freshness distribution for dashboard charts
     */
    public function getFreshnessDistribution(): array
    {
        $repository = $this->entityManager->getRepository(Flower::class);
        
        $result = $repository->createQueryBuilder('f')
            ->select('f.freshnessStatus, COUNT(f.id) as count')
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
     */
    public function getTotalSavings(): array
    {
        $repository = $this->entityManager->getRepository(Flower::class);
        
        $discountedFlowers = $repository->createQueryBuilder('f')
            ->where('f.freshnessStatus = :lastSale')
            ->andWhere('f.discountPrice IS NOT NULL')
            ->setParameter('lastSale', 'Last Sale')
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
     */
    public function getFreshnessByCategory(): array
    {
        $repository = $this->entityManager->getRepository(Flower::class);
        $flowers = $repository->findAll();

        $summary = [];

        foreach ($flowers as $flower) {
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
     */
    public function getFreshnessByFlowerName(): array
    {
        $repository = $this->entityManager->getRepository(Flower::class);
        $flowers = $repository->findAll();

        $summary = [];

        foreach ($flowers as $flower) {
            $name = $flower->getName();
            $status = $flower->getFreshnessStatus() ?: 'Unknown';

            if (!isset($summary[$name])) {
                $summary[$name] = [
                    'Fresh' => 0,
                    'Good' => 0,
                    'Last Sale' => 0,
                    'Expired' => 0,
                    'total' => 0,
                    'category' => $flower->getCategory()
                ];
            }

            if (isset($summary[$name][$status])) {
                $summary[$name][$status]++;
            }
            $summary[$name]['total']++;
        }

        // Sort by flower name
        ksort($summary);

        return $summary;
    }
}