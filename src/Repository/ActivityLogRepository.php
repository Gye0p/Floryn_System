<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Find logs with optional filters
     */
    public function findWithFilters(?string $username = null, ?string $action = null, ?\DateTime $date = null)
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        if ($username) {
            $qb->andWhere('a.username LIKE :username')
                ->setParameter('username', '%' . $username . '%');
        }

        if ($action) {
            $qb->andWhere('a.action = :action')
                ->setParameter('action', $action);
        }

        if ($date) {
            $qb->andWhere('DATE(a.createdAt) = DATE(:date)')
                ->setParameter('date', $date);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get recent activity logs
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
