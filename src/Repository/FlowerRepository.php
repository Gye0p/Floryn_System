<?php

namespace App\Repository;

use App\Entity\Flower;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Flower>
 */
class FlowerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Flower::class);
    }

    /**
     * Find flowers with stock below a given threshold
     * Excludes flowers that are already Sold Out (0 stock) or Unavailable
     *
     * @return Flower[]
     */
    public function findLowStock(int $threshold = 5): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.stockQuantity < :threshold')
            ->andWhere('f.stockQuantity > 0')
            ->andWhere('f.status != :soldOut')
            ->andWhere('f.status != :unavailable')
            ->setParameter('threshold', $threshold)
            ->setParameter('soldOut', 'Sold Out')
            ->setParameter('unavailable', 'Unavailable')
            ->orderBy('f.stockQuantity', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find flowers expiring within a given number of days
     *
     * @return Flower[]
     */
    public function findExpiringSoon(int $days = 3): array
    {
        $futureDate = new \DateTime("+{$days} days");
        $now = new \DateTime();

        return $this->createQueryBuilder('f')
            ->where('f.expiryDate BETWEEN :now AND :future')
            ->andWhere('f.status = :status')
            ->setParameter('now', $now)
            ->setParameter('future', $futureDate)
            ->setParameter('status', 'Available')
            ->orderBy('f.expiryDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
