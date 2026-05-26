<?php

namespace App\Controller;

use App\Entity\Bouquet;
use App\Entity\FlowerBatch;
use App\Entity\Payment;
use App\Entity\Reservation;
use App\Entity\ReservationDetail;
use App\Entity\User;
use App\Repository\BouquetRepository;
use App\Repository\FlowerBatchRepository;
use App\Repository\FlowerRepository;
use App\Repository\PaymentRepository;
use App\Repository\ReservationRepository;
use App\Service\FcmNotificationService;
use App\Service\PaymentSyncService;
use App\Service\WebSocketNotifier;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Customer-scoped API endpoints for the Floryn mobile app.
 * All routes require a valid JWT token with ROLE_CUSTOMER.
 */
#[Route('/api/customer', name: 'api_customer_')]
#[IsGranted('ROLE_CUSTOMER')]
class ApiCustomerController extends AbstractController
{
    // ── Profile ──

    /**
     * GET /api/customer/me — View own profile
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'phone' => $user->getPhone(),
            'address' => $user->getAddress(),
            'roles' => $user->getRoles(),
            'memberSince' => $user->getCreatedAt()?->format('Y-m-d'),
        ]);
    }

    /**
     * PUT /api/customer/me — Update own profile
     * Body: { "full_name": "...", "phone": "...", "address": "..." }
     */
    #[Route('/me', name: 'me_update', methods: ['PUT'])]
    public function updateProfile(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['full_name'])) {
            $user->setFullName($data['full_name']);
        }
        if (isset($data['phone'])) {
            $user->setPhone($data['phone']);
        }
        if (isset($data['address'])) {
            $user->setAddress($data['address']);
        }

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json([
                'error' => 'Validation failed.',
                'details' => $errorMessages,
            ], Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        return $this->json([
            'message' => 'Profile updated successfully.',
            'user' => [
                'id' => $user->getId(),
                'fullName' => $user->getFullName(),
                'phone' => $user->getPhone(),
                'address' => $user->getAddress(),
            ],
        ]);
    }

    // ── Flowers (catalog browsing) ──

    /**
     * GET /api/customer/flowers — Browse available flowers
     * Optional query params: ?category=Roses&search=red
     */
    #[Route('/flowers', name: 'flowers', methods: ['GET'])]
    public function flowers(Request $request, FlowerRepository $flowerRepo): JsonResponse
    {
        $qb = $flowerRepo->createQueryBuilder('f')
            ->where('f.status = :status')
            ->andWhere('f.stockQuantity > 0')
            ->setParameter('status', 'Available')
            ->orderBy('f.name', 'ASC');

        $category = $request->query->get('category');
        if ($category) {
            $qb->andWhere('f.category = :category')
                ->setParameter('category', $category);
        }

        $search = $request->query->get('search');
        if ($search) {
            $qb->andWhere('LOWER(f.name) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        $flowers = $qb->getQuery()->getResult();
        $data = [];

        foreach ($flowers as $flower) {
            $data[] = [
                'id'              => $flower->getId(),
                'name'            => $flower->getName(),
                'category'        => $flower->getCategory(),
                'price'           => $flower->getPrice(),
                'discountPrice'   => $flower->getDiscountPrice(),
                'stockQuantity'   => $flower->getStockQuantity(),
                'freshnessStatus' => $flower->getFreshnessStatus(),
                'status'          => $flower->getStatus(),
                'imageFilename'   => $flower->getImageFilename(),
                'imageUrl'        => $flower->getImageFilename()
                    ? '/uploads/flowers/' . $flower->getImageFilename()
                    : null,
                'dateReceived'    => $flower->getDateReceived()?->format('Y-m-d'),
                'expiryDate'      => $flower->getExpiryDate()?->format('Y-m-d'),
                'supplier'        => $flower->getSupplier()?->getSupplierName(),
            ];
        }

        return $this->json(['flowers' => $data, 'total' => count($data)]);
    }

    /**
     * GET /api/customer/flowers/{id} — Single flower detail
     */
    #[Route('/flowers/{id}', name: 'flower_detail', methods: ['GET'])]
    public function flowerDetail(int $id, FlowerRepository $flowerRepo): JsonResponse
    {
        $flower = $flowerRepo->find($id);

        if (!$flower) {
            return $this->json(['error' => 'Flower not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $flower->getId(),
            'name' => $flower->getName(),
            'category' => $flower->getCategory(),
            'price' => $flower->getPrice(),
            'discountPrice' => $flower->getDiscountPrice(),
            'stockQuantity' => $flower->getStockQuantity(),
            'freshnessStatus' => $flower->getFreshnessStatus(),
            'status' => $flower->getStatus(),
            'imageFilename' => $flower->getImageFilename(),
            'dateReceived' => $flower->getDateReceived()?->format('Y-m-d'),
            'expiryDate' => $flower->getExpiryDate()?->format('Y-m-d'),
            'supplier' => $flower->getSupplier()?->getSupplierName(),
        ]);
    }

    /**
     * GET /api/customer/categories — List distinct flower categories
     */
    #[Route('/categories', name: 'categories', methods: ['GET'])]
    public function categories(FlowerRepository $flowerRepo): JsonResponse
    {
        $categories = $flowerRepo->createQueryBuilder('f')
            ->select('DISTINCT f.category')
            ->where('f.status = :status')
            ->andWhere('f.stockQuantity > 0')
            ->setParameter('status', 'Available')
            ->orderBy('f.category', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return $this->json(['categories' => $categories]);
    }

    // ── Reservations (own orders) ──

    /**
     * GET /api/customer/reservations — View own reservations
     */
    #[Route('/reservations', name: 'reservations', methods: ['GET'])]
    public function reservations(ReservationRepository $reservationRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $reservations = $reservationRepo->findBy(
            ['customer' => $user],
            ['dateReserved' => 'DESC']
        );

        $data = [];
        foreach ($reservations as $reservation) {
            $items = [];
            foreach ($reservation->getReservationDetails() as $detail) {
                $items[] = [
                    'flowerName' => $detail->getFlower()?->getName(),
                    'quantity' => $detail->getQuantity(),
                    'unitPrice' => $detail->getQuantity() > 0 ? $detail->getSubtotal() / $detail->getQuantity() : 0,
                    'subtotal' => $detail->getSubtotal(),
                ];
            }

            $data[] = [
                'id' => $reservation->getId(),
                'pickupDate' => $reservation->getPickupDate()?->format('Y-m-d'),
                'totalAmount' => $reservation->getTotalAmount(),
                'paymentStatus' => $this->resolvePaymentStatus($reservation),
                'reservationStatus' => $reservation->getReservationStatus(),
                'dateReserved' => $reservation->getDateReserved()?->format('Y-m-d H:i:s'),
                'items' => $items,
            ];
        }

        return $this->json(['reservations' => $data, 'total' => count($data)]);
    }

    /**
     * POST /api/customer/reservations — Create a reservation for the logged-in customer
     * Body: { "items": [{ "flowerId": 1, "quantity": 2 }], "pickupDate": "YYYY-MM-DD" }
     */
    #[Route('/reservations', name: 'reservation_create', methods: ['POST'])]
    public function createReservation(
        Request $request,
        FlowerRepository $flowerRepo,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        WebSocketNotifier $webSocketNotifier,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $items = $data['items'] ?? null;
        if (!is_array($items) || count($items) === 0) {
            return $this->json(['error' => 'At least one reservation item is required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $pickupDate = !empty($data['pickupDate'])
                ? new \DateTime((string) $data['pickupDate'])
                : new \DateTime('tomorrow');
        } catch (\Throwable) {
            return $this->json(['error' => 'Pickup date must use YYYY-MM-DD format.'], Response::HTTP_BAD_REQUEST);
        }

        $reservation = (new Reservation())
            ->setCustomer($user)
            ->setPickupDate($pickupDate)
            ->setPaymentStatus('Unpaid')
            ->setReservationStatus('Pending')
            ->setDateReserved(new \DateTime());

        $em->beginTransaction();

        try {
            $totalAmount = 0.0;
            $batchRepo = $em->getRepository(FlowerBatch::class);

            foreach ($items as $item) {
                $flowerId = $item['flowerId'] ?? null;
                $quantity = (int) ($item['quantity'] ?? 0);

                if (!$flowerId || $quantity < 1) {
                    $em->rollback();

                    return $this->json([
                        'error' => 'Each item must include a valid flowerId and quantity.',
                    ], Response::HTTP_BAD_REQUEST);
                }

                $flower = $flowerRepo->find($flowerId);
                if (!$flower) {
                    $em->rollback();

                    return $this->json(['error' => 'Flower not found.'], Response::HTTP_NOT_FOUND);
                }

                $em->lock($flower, LockMode::PESSIMISTIC_WRITE);
                $em->refresh($flower);

                if ($flower->getStatus() !== 'Available' || $flower->getStockQuantity() < $quantity) {
                    $em->rollback();

                    return $this->json([
                        'error' => sprintf(
                            'Insufficient stock for "%s": requested %d, available %d.',
                            $flower->getName(),
                            $quantity,
                            $flower->getStockQuantity()
                        ),
                    ], Response::HTTP_BAD_REQUEST);
                }

                $price = $flower->getEffectivePrice();
                $subtotal = $price * $quantity;

                $detail = (new ReservationDetail())
                    ->setFlower($flower)
                    ->setQuantity($quantity)
                    ->setSubtotal($subtotal);

                $reservation->addReservationDetail($detail);
                $em->persist($detail);

                if ($flower->usesActiveBatchStock()) {
                    $deducted = $batchRepo->deductStock($flower, $quantity);
                    if ($deducted < $quantity) {
                        $em->rollback();

                        return $this->json([
                            'error' => sprintf('Insufficient batch stock for "%s".', $flower->getName()),
                        ], Response::HTTP_BAD_REQUEST);
                    }
                } else {
                    $flower->setStockQuantity($flower->getStockQuantity() - $quantity);
                }

                if ($flower->getStockQuantity() <= 0) {
                    $flower->setStatus('Sold Out');
                }

                $totalAmount += $subtotal;
            }

            $reservation->setTotalAmount($totalAmount);

            $errors = $validator->validate($reservation);
            if (count($errors) > 0) {
                $em->rollback();
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }

                return $this->json([
                    'error' => 'Validation failed.',
                    'details' => $errorMessages,
                ], Response::HTTP_BAD_REQUEST);
            }

            $em->persist($reservation);
            $em->flush();
            $em->commit();

            $webSocketNotifier->broadcastReservationUpdate(
                $reservation->getId(),
                $user->getId(),
                $reservation->getReservationStatus(),
                'reservation_created'
            );

            $responseItems = [];
            foreach ($reservation->getReservationDetails() as $detail) {
                $responseItems[] = [
                    'flowerName' => $detail->getFlower()?->getName(),
                    'quantity' => $detail->getQuantity(),
                    'unitPrice' => $detail->getQuantity() > 0 ? $detail->getSubtotal() / $detail->getQuantity() : 0,
                    'subtotal' => $detail->getSubtotal(),
                ];
            }

            return $this->json([
                'message' => 'Reservation created successfully.',
                'reservation' => [
                    'id' => $reservation->getId(),
                    'pickupDate' => $reservation->getPickupDate()?->format('Y-m-d'),
                    'totalAmount' => $reservation->getTotalAmount(),
                    'paymentStatus' => $reservation->getPaymentStatus(),
                    'reservationStatus' => $reservation->getReservationStatus(),
                    'dateReserved' => $reservation->getDateReserved()?->format('Y-m-d H:i:s'),
                    'items' => $responseItems,
                ],
            ], Response::HTTP_CREATED);
        } catch (\Throwable) {
            if ($em->getConnection()->isTransactionActive()) {
                $em->rollback();
            }

            return $this->json([
                'error' => 'An error occurred while creating the reservation.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/customer/reservations/{id} — View single reservation detail
     */
    #[Route('/reservations/{id}', name: 'reservation_detail', methods: ['GET'])]
    public function reservationDetail(int $id, ReservationRepository $reservationRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $reservation = $reservationRepo->findOneBy(['id' => $id, 'customer' => $user]);

        if (!$reservation) {
            return $this->json(['error' => 'Reservation not found.'], Response::HTTP_NOT_FOUND);
        }

        $items = [];
        foreach ($reservation->getReservationDetails() as $detail) {
            $items[] = [
                'flowerName' => $detail->getFlower()?->getName(),
                'category' => $detail->getFlower()?->getCategory(),
                'quantity' => $detail->getQuantity(),
                'unitPrice' => $detail->getQuantity() > 0 ? $detail->getSubtotal() / $detail->getQuantity() : 0,
                'subtotal' => $detail->getSubtotal(),
            ];
        }

        return $this->json([
            'id' => $reservation->getId(),
            'pickupDate' => $reservation->getPickupDate()?->format('Y-m-d'),
            'totalAmount' => $reservation->getTotalAmount(),
            'paymentStatus' => $reservation->getPaymentStatus(),
            'reservationStatus' => $reservation->getReservationStatus(),
            'dateReserved' => $reservation->getDateReserved()?->format('Y-m-d H:i:s'),
            'items' => $items,
        ]);
    }

    // ── Bouquets (catalog) ──

    /**
     * GET /api/customer/bouquets — Browse ready-made bouquets
     */
    #[Route('/bouquets', name: 'bouquets', methods: ['GET'])]
    public function bouquets(BouquetRepository $bouquetRepo): JsonResponse
    {
        $bouquets = $bouquetRepo->findByStatus('Ready');
        $data = [];

        foreach ($bouquets as $bouquet) {
            $data[] = $this->serializeBouquet($bouquet);
        }

        return $this->json(['bouquets' => $data, 'total' => count($data)]);
    }

    /**
     * GET /api/customer/bouquets/{id} — Single bouquet detail
     */
    #[Route('/bouquets/{id}', name: 'bouquet_detail', methods: ['GET'])]
    public function bouquetDetail(int $id, BouquetRepository $bouquetRepo): JsonResponse
    {
        $bouquet = $bouquetRepo->find($id);

        if (!$bouquet || $bouquet->getStatus() !== 'Ready') {
            return $this->json(['error' => 'Bouquet not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeBouquet($bouquet, includeItems: true));
    }

    // ── Payments ──

    /**
     * GET /api/customer/payments — Payment history for own reservations
     */
    #[Route('/payments', name: 'payments', methods: ['GET'])]
    public function payments(
        PaymentRepository $paymentRepo,
        ReservationRepository $reservationRepo,
        PaymentSyncService $paymentSync,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $data = [];
        $seenReservationIds = [];

        foreach ($paymentRepo->findForCustomer($user) as $payment) {
            $data[] = $this->serializePayment($payment);
            $reservationId = $payment->getReservation()?->getId();
            if ($reservationId !== null) {
                $seenReservationIds[$reservationId] = true;
            }
        }

        // Reservations marked Paid in admin without a Payment row (legacy / edit form only)
        $paidReservations = $reservationRepo->findBy([
            'customer' => $user,
            'paymentStatus' => 'Paid',
        ]);

        foreach ($paidReservations as $reservation) {
            $reservationId = $reservation->getId();
            if ($reservationId === null || isset($seenReservationIds[$reservationId])) {
                continue;
            }

            $payment = $paymentSync->ensurePaymentRecord($reservation, flush: true);
            if ($payment !== null) {
                $data[] = $this->serializePayment($payment);
                $seenReservationIds[$reservationId] = true;
            }
        }

        usort($data, static fn (array $a, array $b) => strcmp(
            (string) ($b['paymentDate'] ?? ''),
            (string) ($a['paymentDate'] ?? '')
        ));

        return $this->json(['payments' => $data, 'total' => count($data)]);
    }

    /**
     * GET /api/customer/payments/{id} — Single payment detail
     */
    #[Route('/payments/{id}', name: 'payment_detail', methods: ['GET'])]
    public function paymentDetail(int $id, PaymentRepository $paymentRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $payment = $paymentRepo->findOneForCustomer($id, $user);
        if (!$payment) {
            return $this->json(['error' => 'Payment not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializePayment($payment, detailed: true));
    }

    /**
     * POST /api/customer/reservations/{id}/cancel — Cancel own pending/confirmed reservation
     */
    #[Route('/reservations/{id}/cancel', name: 'reservation_cancel', methods: ['POST'])]
    public function cancelReservation(
        int $id,
        ReservationRepository $reservationRepo,
        EntityManagerInterface $em,
        WebSocketNotifier $webSocketNotifier,
        FcmNotificationService $fcmNotification,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $reservation = $reservationRepo->findOneBy(['id' => $id, 'customer' => $user]);
        if (!$reservation) {
            return $this->json(['error' => 'Reservation not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($reservation->getReservationStatus() === 'Cancelled') {
            return $this->json(['error' => 'Reservation is already cancelled.'], Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($reservation->getReservationStatus(), ['Pending', 'Confirmed'], true)) {
            return $this->json([
                'error' => 'Only pending or confirmed reservations can be cancelled.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($reservation->getPaymentStatus() === 'Paid') {
            return $this->json([
                'error' => 'Paid reservations cannot be cancelled via the app. Please contact the shop.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $em->beginTransaction();

        try {
            $batchRepo = $em->getRepository(FlowerBatch::class);
            foreach ($reservation->getReservationDetails() as $detail) {
                $flower = $detail->getFlower();
                if ($flower === null) {
                    continue;
                }
                if ($flower->usesActiveBatchStock()) {
                    $batchRepo->restoreStock($flower, $detail->getQuantity());
                } else {
                    $flower->setStockQuantity($flower->getStockQuantity() + $detail->getQuantity());
                }
                if ($flower->getStockQuantity() > 0 && $flower->getStatus() === 'Sold Out') {
                    $flower->setStatus('Available');
                }
            }

            $reservation->setReservationStatus('Cancelled');
            $em->flush();
            $em->commit();

            $fcmNotification->sendReservationStatusUpdate($user, $reservation);
            $webSocketNotifier->broadcastReservationUpdate(
                $reservation->getId(),
                $user->getId(),
                'Cancelled',
                'reservation_updated'
            );

            return $this->json([
                'message' => 'Reservation cancelled successfully.',
                'reservation' => [
                    'id' => $reservation->getId(),
                    'reservationStatus' => $reservation->getReservationStatus(),
                    'paymentStatus' => $reservation->getPaymentStatus(),
                ],
            ]);
        } catch (\Throwable) {
            if ($em->getConnection()->isTransactionActive()) {
                $em->rollback();
            }

            return $this->json([
                'error' => 'An error occurred while cancelling the reservation.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ── Notifications ──

    /**
     * GET /api/customer/notifications — View own notification history
     */
    #[Route('/notifications', name: 'notifications', methods: ['GET'])]
    public function notifications(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = [];
        foreach ($user->getNotificationLogs() as $log) {
            $data[] = [
                'id'       => $log->getId(),
                'message'  => $log->getMessage(),
                'channel'  => $log->getChannel(),
                'status'   => $log->getStatus(),
                'dateSent' => $log->getDateSent()?->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json(['notifications' => $data, 'total' => count($data)]);
    }

    // ── FCM Token ──

    /**
     * POST /api/customer/fcm-token — Register or update device FCM token
     * Body: { "token": "<fcm_device_token>" }
     */
    #[Route('/fcm-token', name: 'fcm_token', methods: ['POST'])]
    public function registerFcmToken(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $token = trim($data['token'] ?? '');

        if (empty($token)) {
            return $this->json(['error' => 'FCM token is required.'], Response::HTTP_BAD_REQUEST);
        }

        $user->setFcmToken($token);
        $em->flush();

        return $this->json(['message' => 'FCM token registered successfully.']);
    }

    // ── Mercure Real-Time Token ──

    /**
     * GET /api/customer/mercure-token
     *
     * Returns a signed Mercure subscriber JWT scoped to this customer's
     * reservation topic (/reservations/{userId}), plus the hub URL.
     * The React Native app uses this to open an EventSource connection
     * and receive instant push updates when a reservation status changes.
     */
    #[Route('/mercure-token', name: 'mercure_token', methods: ['GET'])]
    public function mercureToken(HubInterface $hub, TokenFactoryInterface $tokenFactory): JsonResponse
    {
        /** @var User $user */
        $user  = $this->getUser();
        $topic = '/reservations/' . $user->getId();

        return $this->json([
            'token'   => $tokenFactory->create(subscribe: [$topic]),
            'hub_url' => $hub->getPublicUrl(),
            'topic'   => $topic,
        ]);
    }

    // ── Helpers ──

    private function resolvePaymentStatus(Reservation $reservation): string
    {
        $payment = $reservation->getPayment();
        if ($payment !== null && $payment->getStatus() === 'Paid') {
            return 'Paid';
        }

        return $reservation->getPaymentStatus() ?? 'Unpaid';
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayment(Payment $payment, bool $detailed = false): array
    {
        $reservation = $payment->getReservation();
        $payload = [
            'id' => $payment->getId(),
            'amountPaid' => $payment->getAmountPaid(),
            'paymentMethod' => $payment->getPaymentMethod(),
            'referenceNo' => $payment->getReferenceNo(),
            'status' => $payment->getStatus(),
            'paymentDate' => $payment->getPaymentDate()?->format('Y-m-d'),
            'reservationId' => $reservation?->getId(),
            'reservationStatus' => $reservation?->getReservationStatus(),
        ];

        if ($detailed && $reservation !== null) {
            $items = [];
            foreach ($reservation->getReservationDetails() as $detail) {
                $items[] = [
                    'flowerName' => $detail->getFlower()?->getName(),
                    'quantity' => $detail->getQuantity(),
                    'subtotal' => $detail->getSubtotal(),
                ];
            }
            $payload['pickupDate'] = $reservation->getPickupDate()?->format('Y-m-d');
            $payload['totalAmount'] = $reservation->getTotalAmount();
            $payload['items'] = $items;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBouquet(Bouquet $bouquet, bool $includeItems = false): array
    {
        $payload = [
            'id' => $bouquet->getId(),
            'name' => $bouquet->getName(),
            'description' => $bouquet->getDescription(),
            'notes' => $bouquet->getNotes(),
            'status' => $bouquet->getStatus(),
            'totalPrice' => $bouquet->getTotalPrice(),
            'createdAt' => $bouquet->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];

        if ($includeItems) {
            $items = [];
            foreach ($bouquet->getItems() as $item) {
                $items[] = [
                    'flowerName' => $item->getFlower()?->getName(),
                    'quantity' => $item->getQuantity(),
                ];
            }
            $payload['items'] = $items;
        }

        return $payload;
    }
}
