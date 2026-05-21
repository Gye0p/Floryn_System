<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * @return Payment[]
     */
    public function findForCustomer(User $customer): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.reservation', 'r')
            ->addSelect('r')
            ->where('r.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('p.paymentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForCustomer(int $paymentId, User $customer): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.reservation', 'r')
            ->where('p.id = :id')
            ->andWhere('r.customer = :customer')
            ->setParameter('id', $paymentId)
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getOneOrNullResult();
    }

    //    /**
    //     * @return Payment[] Returns an array of Payment objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Payment
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
