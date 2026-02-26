<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Flower;
use App\Entity\Payment;
use App\Entity\Reservation;
use App\Entity\ReservationDetail;
use App\Repository\CustomerRepository;
use App\Repository\FlowerBatchRepository;
use App\Repository\FlowerRepository;
use App\Service\ActivityLogService;
use App\Service\EmailNotificationService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos')]
#[IsGranted('ROLE_STAFF')]
class PosController extends AbstractController
{
    #[Route('', name: 'app_pos', methods: ['GET'])]
    public function index(FlowerRepository $flowerRepository, CustomerRepository $customerRepository): Response
    {
        // Only show flowers that are available and in stock
        $flowers = $flowerRepository->createQueryBuilder('f')
            ->where('f.stockQuantity > 0')
            ->andWhere('f.status != :soldOut')
            ->andWhere('f.status != :unavailable')
            ->setParameter('soldOut', 'Sold Out')
            ->setParameter('unavailable', 'Unavailable')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();

        $customers = $customerRepository->findBy([], ['fullName' => 'ASC']);

        return $this->render('pos/index.html.twig', [
            'flowers' => $flowers,
            'customers' => $customers,
        ]);
    }

    #[Route('/search-flowers', name: 'app_pos_search_flowers', methods: ['GET'])]
    public function searchFlowers(Request $request, FlowerRepository $flowerRepository): JsonResponse
    {
        $query = $request->query->get('q', '');

        $flowers = $flowerRepository->createQueryBuilder('f')
            ->where('f.stockQuantity > 0')
            ->andWhere('f.status != :soldOut')
            ->andWhere('f.status != :unavailable')
            ->andWhere('f.name LIKE :q OR f.category LIKE :q')
            ->setParameter('soldOut', 'Sold Out')
            ->setParameter('unavailable', 'Unavailable')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($flowers as $flower) {
            $effectivePrice = $flower->getDiscountPrice() > 0 ? $flower->getDiscountPrice() : $flower->getPrice();
            $data[] = [
                'id' => $flower->getId(),
                'name' => $flower->getName(),
                'category' => $flower->getCategory(),
                'price' => $flower->getPrice(),
                'discountPrice' => $flower->getDiscountPrice(),
                'effectivePrice' => $effectivePrice,
                'stock' => $flower->getStockQuantity(),
                'freshness' => $flower->getFreshnessStatus(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/search-customers', name: 'app_pos_search_customers', methods: ['GET'])]
    public function searchCustomers(Request $request, CustomerRepository $customerRepository): JsonResponse
    {
        $query = $request->query->get('q', '');

        $customers = $customerRepository->createQueryBuilder('c')
            ->where('c.fullName LIKE :q OR c.phone LIKE :q OR c.email LIKE :q')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('c.fullName', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($customers as $customer) {
            $data[] = [
                'id' => $customer->getId(),
                'fullName' => $customer->getFullName(),
                'phone' => $customer->getPhone(),
                'email' => $customer->getEmail(),
                'address' => $customer->getAddress(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/checkout', name: 'app_pos_checkout', methods: ['POST'])]
    public function checkout(
        Request $request,
        EntityManagerInterface $em,
        FlowerRepository $flowerRepository,
        CustomerRepository $customerRepository,
        ActivityLogService $activityLog,
        EmailNotificationService $emailNotification
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Validate CSRF token
        if (!$this->isCsrfTokenValid('pos_checkout', $data['_token'] ?? '')) {
            return $this->json(['success' => false, 'error' => 'Invalid security token. Please refresh the page.'], 403);
        }

        // Validate required fields
        if (empty($data['items']) || !is_array($data['items'])) {
            return $this->json(['success' => false, 'error' => 'Cart is empty.'], 400);
        }
        if (empty($data['paymentMethod'])) {
            return $this->json(['success' => false, 'error' => 'Payment method is required.'], 400);
        }

        $em->beginTransaction();

        try {
            // 1. Resolve or create Customer
            $customer = null;
            if (!empty($data['customerId'])) {
                $customer = $customerRepository->find($data['customerId']);
            }

            if (!$customer) {
                // Walk-in customer — create a new one
                $customerName = trim($data['customerName'] ?? 'Walk-in Customer');
                $customerPhone = trim($data['customerPhone'] ?? '');
                $customerEmail = trim($data['customerEmail'] ?? '');

                if (empty($customerName)) {
                    $customerName = 'Walk-in Customer';
                }

                $customer = new Customer();
                $customer->setFullName($customerName);
                $customer->setPhone($customerPhone ?: '+639000000000');
                $customer->setEmail($customerEmail ?: 'walkin-' . time() . '@floryngarden.local');
                $customer->setAddress($data['customerAddress'] ?? 'Walk-in');
                $customer->setDateRegistered(new \DateTime());
                $em->persist($customer);
            }

            // 2. Create Reservation
            $reservation = new Reservation();
            $reservation->setCustomer($customer);
            $reservation->setDateReserved(new \DateTime());
            $reservation->setPickupDate(new \DateTime()); // Walk-in = immediate pickup
            $reservation->setReservationStatus('Completed');
            $reservation->setPaymentStatus('Paid');

            // 3. Create ReservationDetails and validate stock
            $totalAmount = 0;
            $stockErrors = [];

            foreach ($data['items'] as $item) {
                $flower = $flowerRepository->find($item['flowerId'] ?? 0);
                if (!$flower) {
                    $stockErrors[] = 'Flower not found (ID: ' . ($item['flowerId'] ?? '?') . ')';
                    continue;
                }

                // Lock flower row to prevent concurrent overselling
                $em->lock($flower, LockMode::PESSIMISTIC_WRITE);
                $em->refresh($flower);

                $qty = (int) ($item['quantity'] ?? 0);
                if ($qty <= 0) {
                    $stockErrors[] = sprintf('Invalid quantity for "%s".', $flower->getName());
                    continue;
                }

                if ($flower->getStockQuantity() < $qty) {
                    $stockErrors[] = sprintf(
                        'Insufficient stock for "%s": requested %d, available %d.',
                        $flower->getName(),
                        $qty,
                        $flower->getStockQuantity()
                    );
                    continue;
                }

                $effectivePrice = $flower->getDiscountPrice() > 0 ? $flower->getDiscountPrice() : $flower->getPrice();
                $subtotal = $effectivePrice * $qty;

                $detail = new ReservationDetail();
                $detail->setFlower($flower);
                $detail->setReservation($reservation);
                $detail->setQuantity($qty);
                $detail->setSubtotal($subtotal);
                $em->persist($detail);

                // Deduct stock — use FIFO batch deduction if batches exist
                $batchRepo = $em->getRepository(\App\Entity\FlowerBatch::class);
                if (!$flower->getBatches()->isEmpty()) {
                    $batchRepo->deductStock($flower, $qty);
                } else {
                    $flower->setStockQuantity($flower->getStockQuantity() - $qty);
                }

                // Mark as Sold Out if no stock remaining (lifecycle hook handles this too)
                if ($flower->getStockQuantity() <= 0) {
                    $flower->setStatus('Sold Out');
                }

                $totalAmount += $subtotal;
            }

            if (!empty($stockErrors)) {
                $em->rollback();
                return $this->json(['success' => false, 'error' => implode(' ', $stockErrors)], 400);
            }

            if ($totalAmount <= 0) {
                $em->rollback();
                return $this->json(['success' => false, 'error' => 'Total amount must be greater than zero.'], 400);
            }

            $reservation->setTotalAmount($totalAmount);
            $em->persist($reservation);

            // 4. Create Payment
            $payment = new Payment();
            $payment->setReservation($reservation);
            $payment->setAmountPaid($totalAmount);
            $payment->setPaymentDate(new \DateTime());
            $payment->setPaymentMethod($data['paymentMethod']);
            $payment->setReferenceNo('POS-' . date('Ymd') . '-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT));
            $payment->setStatus('Paid');
            $em->persist($payment);

            // 5. Flush everything in one transaction
            $em->flush();
            $em->commit();

            // 6. Log activity
            $activityLog->logCreate('POS Sale', $reservation->getId(), 'POS Sale #' . $reservation->getId() . ' for ' . $customer->getFullName());

            // 7. Send email confirmation (non-blocking)
            try {
                $emailNotification->sendReservationConfirmation($reservation);
            } catch (\Exception $e) {
                // Don't fail the sale if email fails
            }

            return $this->json([
                'success' => true,
                'reservationId' => $reservation->getId(),
                'paymentId' => $payment->getId(),
                'referenceNo' => $payment->getReferenceNo(),
                'totalAmount' => $totalAmount,
                'customerName' => $customer->getFullName(),
                'message' => 'Sale completed successfully!',
            ]);
        } catch (\Exception $e) {
            $em->rollback();
            return $this->json(['success' => false, 'error' => 'An error occurred while processing the sale. Please try again.'], 500);
        }
    }
}
