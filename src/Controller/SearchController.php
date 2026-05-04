<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Flower;
use App\Entity\Payment;
use App\Entity\Reservation;
use App\Entity\Supplier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
class SearchController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Live search API – returns JSON results for the topbar dropdown.
     */
    #[Route('/search/autocomplete', name: 'app_search_autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request): JsonResponse
    {
        $q = trim($request->query->getString('q', ''));

        if (mb_strlen($q) < 2) {
            return $this->json([]);
        }

        $results = [];

        // Search Flowers
        $flowers = $this->em->getRepository(Flower::class)
            ->createQueryBuilder('f')
            ->where('LOWER(f.name) LIKE :q')
            ->orWhere('LOWER(f.category) LIKE :q')
            ->setParameter('q', '%' . mb_strtolower($q) . '%')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($flowers as $flower) {
            $results[] = [
                'type' => 'Flower',
                'icon' => '🌺',
                'title' => $flower->getName(),
                'subtitle' => $flower->getCategory() . ' — ₱' . number_format($flower->getPrice(), 2),
                'url' => $this->generateUrl('app_flower_show', ['id' => $flower->getId()]),
            ];
        }

        // Search Customers (ROLE_CUSTOMER users)
        $customers = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->andWhere('LOWER(u.fullName) LIKE :q OR LOWER(u.email) LIKE :q OR LOWER(u.phone) LIKE :q')
            ->setParameter('role', '%ROLE_CUSTOMER%')
            ->setParameter('q', '%' . mb_strtolower($q) . '%')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($customers as $customer) {
            $results[] = [
                'type' => 'Customer',
                'icon' => '👥',
                'title' => $customer->getFullName(),
                'subtitle' => $customer->getEmail(),
                'url' => $this->generateUrl('app_customer_show', ['id' => $customer->getId()]),
            ];
        }

        // Search Suppliers
        $suppliers = $this->em->getRepository(Supplier::class)
            ->createQueryBuilder('s')
            ->where('LOWER(s.supplierName) LIKE :q')
            ->orWhere('LOWER(s.contactPerson) LIKE :q')
            ->orWhere('LOWER(s.email) LIKE :q')
            ->setParameter('q', '%' . mb_strtolower($q) . '%')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($suppliers as $supplier) {
            $results[] = [
                'type' => 'Supplier',
                'icon' => '🚚',
                'title' => $supplier->getSupplierName(),
                'subtitle' => $supplier->getContactPerson(),
                'url' => $this->generateUrl('app_supplier_show', ['id' => $supplier->getId()]),
            ];
        }

        // Search Payments by reference number
        $payments = $this->em->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->where('LOWER(p.referenceNo) LIKE :q')
            ->orWhere('LOWER(p.paymentMethod) LIKE :q')
            ->setParameter('q', '%' . mb_strtolower($q) . '%')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($payments as $payment) {
            $results[] = [
                'type' => 'Payment',
                'icon' => '💰',
                'title' => 'Ref: ' . $payment->getReferenceNo(),
                'subtitle' => $payment->getPaymentMethod() . ' — ₱' . number_format($payment->getAmountPaid(), 2),
                'url' => $this->generateUrl('app_payment_show', ['id' => $payment->getId()]),
            ];
        }

        // Search Reservations by ID or customer name
        $reservations = $this->em->getRepository(Reservation::class)
            ->createQueryBuilder('r')
            ->leftJoin('r.customer', 'rc')
            ->where('LOWER(rc.fullName) LIKE :q')
            ->orWhere('LOWER(r.reservationStatus) LIKE :q')
            ->orWhere('LOWER(r.paymentStatus) LIKE :q')
            ->setParameter('q', '%' . mb_strtolower($q) . '%')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($reservations as $reservation) {
            $results[] = [
                'type' => 'Reservation',
                'icon' => '📦',
                'title' => 'Reservation #' . $reservation->getId(),
                'subtitle' => ($reservation->getCustomer() ? $reservation->getCustomer()->getFullName() : '') .
                    ' — ' . $reservation->getReservationStatus(),
                'url' => $this->generateUrl('app_reservation_show', ['id' => $reservation->getId()]),
            ];
        }

        // Also try numeric ID search across entities
        if (ctype_digit($q)) {
            $id = (int) $q;

            $flower = $this->em->getRepository(Flower::class)->find($id);
            if ($flower && !$this->hasResult($results, 'Flower', $flower->getName())) {
                $results[] = [
                    'type' => 'Flower',
                    'icon' => '🌺',
                    'title' => $flower->getName(),
                    'subtitle' => 'ID #' . $id . ' — ' . $flower->getCategory(),
                    'url' => $this->generateUrl('app_flower_show', ['id' => $id]),
                ];
            }

            $reservation = $this->em->getRepository(Reservation::class)->find($id);
            if ($reservation && !$this->hasResult($results, 'Reservation', 'Reservation #' . $id)) {
                $results[] = [
                    'type' => 'Reservation',
                    'icon' => '📦',
                    'title' => 'Reservation #' . $id,
                    'subtitle' => ($reservation->getCustomer() ? $reservation->getCustomer()->getFullName() : '') .
                        ' — ' . $reservation->getReservationStatus(),
                    'url' => $this->generateUrl('app_reservation_show', ['id' => $id]),
                ];
            }
        }

        return $this->json(array_slice($results, 0, 15));
    }

    /**
     * Full search results page.
     */
    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $q = trim($request->query->getString('q', ''));

        $flowers = [];
        $customers = [];
        $suppliers = [];
        $payments = [];
        $reservations = [];

        if (mb_strlen($q) >= 2) {
            $like = '%' . mb_strtolower($q) . '%';

            $flowers = $this->em->getRepository(Flower::class)
                ->createQueryBuilder('f')
                ->where('LOWER(f.name) LIKE :q')
                ->orWhere('LOWER(f.category) LIKE :q')
                ->setParameter('q', $like)
                ->setMaxResults(20)
                ->getQuery()
                ->getResult();

            $customers = $this->em->getRepository(User::class)
                ->createQueryBuilder('u')
                ->where('u.roles LIKE :role')
                ->andWhere('LOWER(u.fullName) LIKE :q OR LOWER(u.email) LIKE :q OR LOWER(u.phone) LIKE :q')
                ->setParameter('role', '%ROLE_CUSTOMER%')
                ->setParameter('q', $like)
                ->setMaxResults(20)
                ->getQuery()
                ->getResult();

            $suppliers = $this->em->getRepository(Supplier::class)
                ->createQueryBuilder('s')
                ->where('LOWER(s.supplierName) LIKE :q')
                ->orWhere('LOWER(s.contactPerson) LIKE :q')
                ->orWhere('LOWER(s.email) LIKE :q')
                ->setParameter('q', $like)
                ->setMaxResults(20)
                ->getQuery()
                ->getResult();

            $payments = $this->em->getRepository(Payment::class)
                ->createQueryBuilder('p')
                ->where('LOWER(p.referenceNo) LIKE :q')
                ->orWhere('LOWER(p.paymentMethod) LIKE :q')
                ->setParameter('q', $like)
                ->setMaxResults(20)
                ->getQuery()
                ->getResult();

            $reservations = $this->em->getRepository(Reservation::class)
                ->createQueryBuilder('r')
                ->leftJoin('r.customer', 'rc')
                ->where('LOWER(rc.fullName) LIKE :q')
                ->orWhere('LOWER(r.reservationStatus) LIKE :q')
                ->orWhere('LOWER(r.paymentStatus) LIKE :q')
                ->setParameter('q', $like)
                ->setMaxResults(20)
                ->getQuery()
                ->getResult();
        }

        $totalCount = count($flowers) + count($customers) + count($suppliers) + count($payments) + count($reservations);

        return $this->render('search/results.html.twig', [
            'q' => $q,
            'flowers' => $flowers,
            'customers' => $customers,
            'suppliers' => $suppliers,
            'payments' => $payments,
            'reservations' => $reservations,
            'totalCount' => $totalCount,
        ]);
    }

    private function hasResult(array $results, string $type, string $title): bool
    {
        foreach ($results as $r) {
            if ($r['type'] === $type && $r['title'] === $title) {
                return true;
            }
        }
        return false;
    }
}
