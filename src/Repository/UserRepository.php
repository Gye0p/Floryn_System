<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Count users that have ROLE_ADMIN in their roles JSON array
     */
    public function countAdmins(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count users with ROLE_CUSTOMER
     */
    public function countCustomers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_CUSTOMER%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return User[] Returns all users with ROLE_CUSTOMER, ordered by fullName
     */
    public function findCustomers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_CUSTOMER%')
            ->orderBy('u.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a customer user by email.
     */
    public function findCustomerByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('email', $email)
            ->setParameter('role', '%ROLE_CUSTOMER%')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return User[] Returns all users that are pending admin approval
     */
    public function findPendingUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.isApproved = :approved')
            ->setParameter('approved', false)
            ->orderBy('u.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return User[] Returns all approved users
     */
    public function findApprovedUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.isApproved = :approved')
            ->setParameter('approved', true)
            ->orderBy('u.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count users awaiting admin approval
     */
    public function countPendingUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isApproved = :approved')
            ->setParameter('approved', false)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
