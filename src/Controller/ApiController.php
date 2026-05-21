<?php

namespace App\Controller;

use App\Repository\FlowerRepository;
use App\Repository\UserRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Staff/admin API endpoints (inventory dashboard data).
 * Requires ROLE_STAFF — customers must use /api/customer/* instead.
 */
#[Route('/api', name: 'api_')]
#[IsGranted('ROLE_STAFF')]
class ApiController extends AbstractController
{
    /**
     * GET /api/flowers — List all flowers
     */
    #[Route('/flowers', name: 'flowers', methods: ['GET'])]
    public function flowers(FlowerRepository $flowerRepository): JsonResponse
    {
        $flowers = $flowerRepository->findAll();
        $data = [];

        foreach ($flowers as $flower) {
            $data[] = [
                'id' => $flower->getId(),
                'name' => $flower->getName(),
                'category' => $flower->getCategory(),
                'price' => $flower->getPrice(),
                'discountPrice' => $flower->getDiscountPrice(),
                'stockQuantity' => $flower->getStockQuantity(),
                'freshnessStatus' => $flower->getFreshnessStatus(),
                'status' => $flower->getStatus(),
                'dateReceived' => $flower->getDateReceived()?->format('Y-m-d'),
                'expiryDate' => $flower->getExpiryDate()?->format('Y-m-d'),
                'supplier' => $flower->getSupplier()?->getSupplierName(),
            ];
        }

        return $this->json(['flowers' => $data, 'total' => count($data)]);
    }

    /**
     * GET /api/customers — List all customers
     */
    #[Route('/customers', name: 'customers', methods: ['GET'])]
    public function customers(UserRepository $userRepository): JsonResponse
    {
        $customers = $userRepository->findCustomers();
        $data = [];

        foreach ($customers as $customer) {
            $data[] = [
                'id' => $customer->getId(),
                'fullName' => $customer->getFullName(),
                'phone' => $customer->getPhone(),
                'email' => $customer->getEmail(),
                'address' => $customer->getAddress(),
                'dateRegistered' => $customer->getCreatedAt()?->format('Y-m-d'),
                'reservationCount' => $customer->getReservations()->count(),
            ];
        }

        return $this->json(['customers' => $data, 'total' => count($data)]);
    }

    /**
     * GET /api/reservations — List all reservations
     */
    #[Route('/reservations', name: 'reservations', methods: ['GET'])]
    public function reservations(ReservationRepository $reservationRepository): JsonResponse
    {
        $reservations = $reservationRepository->findAll();
        $data = [];

        foreach ($reservations as $reservation) {
            $data[] = [
                'id' => $reservation->getId(),
                'customer' => $reservation->getCustomer()?->getFullName(),
                'pickupDate' => $reservation->getPickupDate()?->format('Y-m-d'),
                'totalAmount' => $reservation->getTotalAmount(),
                'paymentStatus' => $reservation->getPaymentStatus(),
                'reservationStatus' => $reservation->getReservationStatus(),
                'dateReserved' => $reservation->getDateReserved()?->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json(['reservations' => $data, 'total' => count($data)]);
    }

    /**
     * GET /api/dashboard — Dashboard summary stats
     */
    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(
        FlowerRepository $flowerRepository,
        UserRepository $userRepository,
        ReservationRepository $reservationRepository,
    ): JsonResponse {
        return $this->json([
            'totalFlowers' => count($flowerRepository->findAll()),
            'totalCustomers' => $userRepository->countCustomers(),
            'totalReservations' => count($reservationRepository->findAll()),
            'lowStockFlowers' => count($flowerRepository->findLowStock(5)),
        ]);
    }
}
