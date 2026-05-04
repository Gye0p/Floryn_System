<?php

namespace App\Repository;

use App\Entity\Bouquet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bouquet>
 */
class BouquetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bouquet::class);
    }

    /**
     * Find bouquets by status.
     *
     * @return Bouquet[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.status = :status')
            ->setParameter('status', $status)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all bouquets ordered by latest first with their items eagerly loaded.
     *
     * @return Bouquet[]
     */
    public function findAllWithItems(): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.items', 'i')
            ->addSelect('i')
            ->leftJoin('i.flower', 'f')
            ->addSelect('f')
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
