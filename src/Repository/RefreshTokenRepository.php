<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findValidByHash(string $tokenHash): ?RefreshToken
    {
        return $this->createQueryBuilder('t')
            ->where('t.tokenHash = :hash')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('hash', $tokenHash)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deleteExpiredForUser(User $user): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->where('t.user = :user')
            ->andWhere('t.expiresAt <= :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
