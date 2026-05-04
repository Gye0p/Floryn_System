<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\FlowerRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
                'imageFilename'   => $flower->getImageFilename(),
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
                'paymentStatus' => $reservation->getPaymentStatus(),
                'reservationStatus' => $reservation->getReservationStatus(),
                'dateReserved' => $reservation->getDateReserved()?->format('Y-m-d H:i:s'),
                'items' => $items,
            ];
        }

        return $this->json(['reservations' => $data, 'total' => count($data)]);
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
                'id' => $log->getId(),
                'message' => $log->getMessage(),
                'channel' => $log->getChannel(),
                'status' => $log->getStatus(),
                'dateSent' => $log->getDateSent()?->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json(['notifications' => $data, 'total' => count($data)]);
    }
}
