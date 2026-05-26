<?php

namespace App\Service;

use App\Entity\Payment;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Keeps reservation.paymentStatus and Payment rows in sync for the mobile app.
 */
class PaymentSyncService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Create a Payment row when staff marked a reservation Paid without using Payments.
     */
    public function ensurePaymentRecord(Reservation $reservation, bool $flush = false): ?Payment
    {
        if ($reservation->getPayment() !== null) {
            return $reservation->getPayment();
        }

        if ($reservation->getPaymentStatus() !== 'Paid') {
            return null;
        }

        $payment = (new Payment())
            ->setReservation($reservation)
            ->setAmountPaid((float) $reservation->getTotalAmount())
            ->setPaymentDate(new \DateTime())
            ->setPaymentMethod('Cash')
            ->setReferenceNo(sprintf('ADM-%d-%s', $reservation->getId() ?? 0, date('Ymd')))
            ->setStatus('Paid');

        $reservation->setPayment($payment);
        $this->em->persist($payment);

        if ($flush) {
            $this->em->flush();
        }

        return $payment;
    }

    public function resolvePaymentStatus(Reservation $reservation): string
    {
        $payment = $reservation->getPayment();
        if ($payment !== null && $payment->getStatus() === 'Paid') {
            return 'Paid';
        }

        return $reservation->getPaymentStatus() ?? 'Unpaid';
    }
}
