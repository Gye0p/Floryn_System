<?php

namespace App\Controller;

use App\Repository\FlowerRepository;
use App\Repository\SupplierRepository;
use App\Repository\CustomerRepository;
use App\Repository\ReservationRepository;
use App\Repository\PaymentRepository;
use App\Repository\UserRepository;
use App\Repository\ActivityLogRepository;
use App\Service\FlowerStatusUpdater;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
class AdminDashboardController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function index(
        FlowerRepository $flowerRepo,
        SupplierRepository $supplierRepo,
        CustomerRepository $customerRepo,
        ReservationRepository $reservationRepo,
        PaymentRepository $paymentRepo,
        UserRepository $userRepo,
        ActivityLogRepository $activityLogRepo,
        FlowerStatusUpdater $flowerStatusUpdater
    ): Response {
        // Read-only freshness stats (status updates handled by app:update-flower-status command)
        $freshnessStats = $flowerStatusUpdater->getFreshnessStats();
        
        $totalFlowers = $flowerRepo->count([]);
        $totalSuppliers = $supplierRepo->count([]);
        $totalCustomers = $customerRepo->count([]);
        $totalReservations = $reservationRepo->count([]);
        $totalPayments = $paymentRepo->count([]);
        
        // User and Activity Stats
        $totalUsers = $userRepo->count([]);
        $totalAdmins = $userRepo->countAdmins();
        $totalStaff = $totalUsers - $totalAdmins;
        $recentLogs = $activityLogRepo->findRecent(10);

        // Get low-stock flowers (quantity < 5)
        $lowStockFlowers = $flowerRepo->findLowStock(5);

        // Get flowers expiring soon
        $expiringSoonFlowers = $flowerStatusUpdater->getExpiringSoonFlowers();
        
        // Get freshness distribution for charts
        $freshnessDistribution = $flowerStatusUpdater->getFreshnessDistribution();

        // Get savings information for discounted flowers
        $savingsData = $flowerStatusUpdater->getTotalSavings();

        // Get freshness breakdown by category and flower name
        $freshnessByCategory = $flowerStatusUpdater->getFreshnessByCategory();
        $freshnessByFlowerName = $flowerStatusUpdater->getFreshnessByFlowerName();

        return $this->render('admin_dashboard/index.html.twig', [
            'totalFlowers' => $totalFlowers,
            'totalSuppliers' => $totalSuppliers,
            'totalCustomers' => $totalCustomers,
            'totalReservations' => $totalReservations,
            'totalPayments' => $totalPayments,
            'totalUsers' => $totalUsers,
            'totalAdmins' => $totalAdmins,
            'totalStaff' => $totalStaff,
            'recentLogs' => $recentLogs,
            'lowStockFlowers' => $lowStockFlowers,
            'expiringSoonFlowers' => $expiringSoonFlowers,
            'freshnessDistribution' => $freshnessDistribution,
            'freshnessStats' => $freshnessStats,
            'savingsData' => $savingsData,
            'freshnessByCategory' => $freshnessByCategory,
            'freshnessByFlowerName' => $freshnessByFlowerName,
        ]);
    }
}
