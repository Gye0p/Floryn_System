<?php

namespace App\Service;

use App\Entity\Bouquet;
use App\Entity\BouquetItem;
use App\Entity\Flower;
use App\Repository\FlowerBatchRepository;
use Doctrine\ORM\EntityManagerInterface;

class BouquetService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FlowerBatchRepository $flowerBatchRepository,
        private ActivityLogService $activityLog,
    ) {}

    /**
     * Add a flower to a bouquet, deducting its stock immediately.
     *
     * @throws \InvalidArgumentException if stock is insufficient or flower is unavailable
     */
    public function addFlowerToBouquet(Bouquet $bouquet, Flower $flower, int $quantity): BouquetItem
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be at least 1.');
        }

        if ($flower->getStockQuantity() < $quantity) {
            throw new \InvalidArgumentException(sprintf(
                'Insufficient stock for "%s": requested %d, available %d.',
                $flower->getName(),
                $quantity,
                $flower->getStockQuantity()
            ));
        }

        if (in_array($flower->getStatus(), ['Sold Out', 'Unavailable'], true)) {
            throw new \InvalidArgumentException(sprintf(
                '"%s" is currently %s and cannot be added to a bouquet.',
                $flower->getName(),
                $flower->getStatus()
            ));
        }

        // Deduct stock using FEFO (First Expiry First Out) if batches exist, else simple deduction
        if (!$flower->getBatches()->isEmpty()) {
            $this->flowerBatchRepository->deductStock($flower, $quantity);
        } else {
            $flower->setStockQuantity($flower->getStockQuantity() - $quantity);
            $flower->syncStatusWithStock();
        }

        // Snapshot the effective unit price at the time of adding
        $unitPrice = $flower->getDiscountPrice() > 0
            ? $flower->getDiscountPrice()
            : $flower->getPrice();

        $item = new BouquetItem();
        $item->setFlower($flower);
        $item->setQuantity($quantity);
        $item->setUnitPrice($unitPrice);
        $item->recalculateSubtotal();

        $bouquet->addItem($item);
        $bouquet->recalculateTotalPrice();

        $this->entityManager->persist($item);

        $this->activityLog->logCreate(
            'BouquetItem',
            $flower->getId(),
            sprintf('Added %dx %s to bouquet "%s"', $quantity, $flower->getName(), $bouquet->getName() ?? 'Draft')
        );

        return $item;
    }

    /**
     * Remove a flower item from a bouquet, restoring the deducted stock.
     */
    public function removeItemFromBouquet(Bouquet $bouquet, BouquetItem $item): void
    {
        $flower = $item->getFlower();
        $quantity = $item->getQuantity();

        // Restore stock
        if ($flower !== null && $quantity > 0) {
            if (!$flower->getBatches()->isEmpty()) {
                $this->flowerBatchRepository->restoreStock($flower, $quantity);
            } else {
                $flower->setStockQuantity($flower->getStockQuantity() + $quantity);
                $flower->syncStatusWithStock();
            }
        }

        $bouquet->removeItem($item);
        $bouquet->recalculateTotalPrice();

        $this->entityManager->remove($item);

        $this->activityLog->logDelete(
            'BouquetItem',
            $item->getId(),
            sprintf('Removed %dx %s from bouquet "%s"', $quantity, $flower?->getName(), $bouquet->getName() ?? 'Draft')
        );
    }

    /**
     * Create and persist a new bouquet. Does NOT flush — caller must flush.
     */
    public function createBouquet(string $name, ?string $description = null, ?string $notes = null): Bouquet
    {
        $bouquet = new Bouquet();
        $bouquet->setName($name);
        $bouquet->setDescription($description);
        $bouquet->setNotes($notes);
        $bouquet->setStatus('Draft');

        $this->entityManager->persist($bouquet);

        $this->activityLog->logCreate('Bouquet', 0, sprintf('Bouquet "%s" created', $name));

        return $bouquet;
    }

    /**
     * Mark a bouquet as "Ready" (all flowers assembled, ready for sale/pickup).
     */
    public function markReady(Bouquet $bouquet): void
    {
        $bouquet->setStatus('Ready');
        $bouquet->recalculateTotalPrice();

        $this->activityLog->logUpdate('Bouquet', $bouquet->getId(), sprintf('Bouquet "%s" marked as Ready', $bouquet->getName()));
    }

    /**
     * Mark a bouquet as "Sold". No further stock changes needed.
     */
    public function markSold(Bouquet $bouquet): void
    {
        $bouquet->setStatus('Sold');

        $this->activityLog->logUpdate('Bouquet', $bouquet->getId(), sprintf('Bouquet "%s" marked as Sold', $bouquet->getName()));
    }

    /**
     * Cancel a bouquet and restore all flower stock.
     * Removes all items and sets status to 'Cancelled'.
     */
    public function cancelBouquet(Bouquet $bouquet): void
    {
        foreach ($bouquet->getItems() as $item) {
            $flower = $item->getFlower();
            $quantity = $item->getQuantity();

            if ($flower !== null && $quantity > 0) {
                if (!$flower->getBatches()->isEmpty()) {
                    $this->flowerBatchRepository->restoreStock($flower, $quantity);
                } else {
                    $flower->setStockQuantity($flower->getStockQuantity() + $quantity);
                    $flower->syncStatusWithStock();
                }
            }
        }

        $bouquet->setStatus('Cancelled');

        $this->activityLog->logUpdate('Bouquet', $bouquet->getId(), sprintf('Bouquet "%s" cancelled — stock restored', $bouquet->getName()));
    }
}
