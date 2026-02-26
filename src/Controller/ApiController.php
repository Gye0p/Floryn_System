<?php

namespace App\Controller;

use App\Repository\FlowerRepository;
use App\Repository\CustomerRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API endpoints for accessing data with JWT authentication.
 * All routes require a valid JWT token in the Authorization header:
 *   Authorization: Bearer <token>
 */
#[Route('/api', name: 'api_')]
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
    public function customers(CustomerRepository $customerRepository): JsonResponse
    {
        $customers = $customerRepository->findAll();
        $data = [];

        foreach ($customers as $customer) {
            $data[] = [
                'id' => $customer->getId(),
                'fullName' => $customer->getFullName(),
                'phone' => $customer->getPhone(),
                'email' => $customer->getEmail(),
                'address' => $customer->getAddress(),
                'dateRegistered' => $customer->getDateRegistered()?->format('Y-m-d'),
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
        CustomerRepository $customerRepository,
        ReservationRepository $reservationRepository,
    ): JsonResponse {
        return $this->json([
            'totalFlowers' => count($flowerRepository->findAll()),
            'totalCustomers' => count($customerRepository->findAll()),
            'totalReservations' => count($reservationRepository->findAll()),
            'lowStockFlowers' => count($flowerRepository->findLowStock(5)),
        ]);
    }
}
