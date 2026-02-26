<?php

namespace App\Controller;

use App\Repository\FlowerRepository;
use App\Repository\CustomerRepository;
use App\Repository\ReservationRepository;
use App\Repository\ReservationDetailRepository;
use App\Repository\PaymentRepository;
use App\Repository\SupplierRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reports')]
#[IsGranted('ROLE_STAFF')]
class ReportController extends AbstractController
{
    #[Route('/', name: 'app_reports_index')]
    public function index(): Response
    {
        return $this->render('reports/index.html.twig');
    }

    #[Route('/sales', name: 'app_reports_sales')]
    public function salesReport(
        Request $request,
        ReservationRepository $reservationRepo,
        PaymentRepository $paymentRepo
    ): Response {
        $startDate = $request->query->get('start_date', date('Y-m-01'));
        $endDate = $request->query->get('end_date', date('Y-m-d'));

        $reservations = $reservationRepo->createQueryBuilder('r')
            ->where('r.dateReserved BETWEEN :start AND :end')
            ->setParameter('start', new \DateTime($startDate))
            ->setParameter('end', new \DateTime($endDate . ' 23:59:59'))
            ->orderBy('r.dateReserved', 'DESC')
            ->getQuery()
            ->getResult();

        $payments = $paymentRepo->createQueryBuilder('p')
            ->where('p.paymentDate BETWEEN :start AND :end')
            ->setParameter('start', new \DateTime($startDate))
            ->setParameter('end', new \DateTime($endDate . ' 23:59:59'))
            ->orderBy('p.paymentDate', 'DESC')
            ->getQuery()
            ->getResult();

        // Calculate statistics
        $totalRevenue = array_reduce($payments, fn($sum, $p) => $sum + $p->getAmountPaid(), 0);
        $totalReservations = count($reservations);
        $avgOrderValue = $totalReservations > 0 ? $totalRevenue / $totalReservations : 0;

        $completedReservations = array_filter($reservations, fn($r) => $r->getReservationStatus() === 'Completed');
        $pendingReservations = array_filter($reservations, fn($r) => $r->getReservationStatus() === 'Pending');
        $cancelledReservations = array_filter($reservations, fn($r) => $r->getReservationStatus() === 'Cancelled');

        return $this->render('reports/sales.html.twig', [
            'reservations' => $reservations,
            'payments' => $payments,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_revenue' => $totalRevenue,
            'total_reservations' => $totalReservations,
            'avg_order_value' => $avgOrderValue,
            'completed_count' => count($completedReservations),
            'pending_count' => count($pendingReservations),
            'cancelled_count' => count($cancelledReservations),
        ]);
    }

    #[Route('/inventory', name: 'app_reports_inventory')]
    public function inventoryReport(FlowerRepository $flowerRepo): Response
    {
        $flowers = $flowerRepo->findAll();

        // Categorize flowers
        $lowStock = array_filter($flowers, fn($f) => $f->getStockQuantity() < 5);
        $outOfStock = array_filter($flowers, fn($f) => $f->getStockQuantity() === 0);
        $expiringSoon = array_filter($flowers, function($f) {
            if (!$f->getExpiryDate()) {
                return false;
            }
            $daysUntilExpiry = $f->getExpiryDate()->diff(new \DateTime())->days;
            return $daysUntilExpiry <= 3 && $f->getExpiryDate() >= new \DateTime();
        });

        // Calculate inventory value
        $totalValue = array_reduce($flowers, function($sum, $f) {
            $price = $f->getDiscountPrice() > 0 ? $f->getDiscountPrice() : $f->getPrice();
            return $sum + ($price * $f->getStockQuantity());
        }, 0);

        // Group by category
        $byCategory = [];
        foreach ($flowers as $flower) {
            $category = $flower->getCategory();
            if (!isset($byCategory[$category])) {
                $byCategory[$category] = ['count' => 0, 'stock' => 0, 'value' => 0];
            }
            $byCategory[$category]['count']++;
            $byCategory[$category]['stock'] += $flower->getStockQuantity();
            $price = $flower->getDiscountPrice() > 0 ? $flower->getDiscountPrice() : $flower->getPrice();
            $byCategory[$category]['value'] += $price * $flower->getStockQuantity();
        }

        return $this->render('reports/inventory.html.twig', [
            'flowers' => $flowers,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'expiring_soon' => $expiringSoon,
            'total_value' => $totalValue,
            'by_category' => $byCategory,
        ]);
    }

    #[Route('/customers', name: 'app_reports_customers')]
    public function customerReport(
        CustomerRepository $customerRepo,
        ReservationRepository $reservationRepo
    ): Response {
        $customers = $customerRepo->findAll();

        $customerStats = [];
        foreach ($customers as $customer) {
            $reservations = $reservationRepo->findBy(['customer' => $customer]);
            $totalSpent = array_reduce($reservations, fn($sum, $r) => $sum + $r->getTotalAmount(), 0);
            
            $customerStats[] = [
                'customer' => $customer,
                'total_orders' => count($reservations),
                'total_spent' => $totalSpent,
                'avg_order_value' => count($reservations) > 0 ? $totalSpent / count($reservations) : 0,
                'last_order_date' => count($reservations) > 0 ? max(array_map(fn($r) => $r->getDateReserved(), $reservations)) : null,
            ];
        }

        // Sort by total spent (descending)
        usort($customerStats, fn($a, $b) => $b['total_spent'] <=> $a['total_spent']);

        return $this->render('reports/customers.html.twig', [
            'customer_stats' => $customerStats,
            'total_customers' => count($customers),
        ]);
    }

    #[Route('/suppliers', name: 'app_reports_suppliers')]
    public function supplierReport(
        SupplierRepository $supplierRepo,
        FlowerRepository $flowerRepo
    ): Response {
        $suppliers = $supplierRepo->findAll();

        $supplierStats = [];
        foreach ($suppliers as $supplier) {
            $flowers = $flowerRepo->findBy(['supplier' => $supplier]);
            $totalStock = array_reduce($flowers, fn($sum, $f) => $sum + $f->getStockQuantity(), 0);
            $totalValue = array_reduce($flowers, function($sum, $f) {
                $price = $f->getDiscountPrice() > 0 ? $f->getDiscountPrice() : $f->getPrice();
                return $sum + ($price * $f->getStockQuantity());
            }, 0);

            $supplierStats[] = [
                'supplier' => $supplier,
                'flower_count' => count($flowers),
                'total_stock' => $totalStock,
                'inventory_value' => $totalValue,
            ];
        }

        return $this->render('reports/suppliers.html.twig', [
            'supplier_stats' => $supplierStats,
            'total_suppliers' => count($suppliers),
        ]);
    }

    #[Route('/export/sales-csv', name: 'app_reports_export_sales_csv')]
    public function exportSalesCsv(
        Request $request,
        ReservationRepository $reservationRepo
    ): Response {
        $startDate = $request->query->get('start_date', date('Y-m-01'));
        $endDate = $request->query->get('end_date', date('Y-m-d'));

        $reservations = $reservationRepo->createQueryBuilder('r')
            ->where('r.dateReserved BETWEEN :start AND :end')
            ->setParameter('start', new \DateTime($startDate))
            ->setParameter('end', new \DateTime($endDate . ' 23:59:59'))
            ->orderBy('r.dateReserved', 'DESC')
            ->getQuery()
            ->getResult();

        $csv = "Reservation ID,Customer,Date Reserved,Pickup Date,Total Amount,Payment Status,Reservation Status\n";
        
        foreach ($reservations as $reservation) {
            $csv .= sprintf(
                "%d,\"%s\",%s,%s,%.2f,\"%s\",\"%s\"\n",
                $reservation->getId(),
                str_replace('"', '""', $reservation->getCustomer()->getFullName()),
                $reservation->getDateReserved()->format('Y-m-d'),
                $reservation->getPickupDate() ? $reservation->getPickupDate()->format('Y-m-d') : '',
                $reservation->getTotalAmount(),
                str_replace('"', '""', $reservation->getPaymentStatus() ?? ''),
                str_replace('"', '""', $reservation->getReservationStatus() ?? '')
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="sales_report_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    #[Route('/export/inventory-csv', name: 'app_reports_export_inventory_csv')]
    public function exportInventoryCsv(FlowerRepository $flowerRepo): Response
    {
        $flowers = $flowerRepo->findAll();

        $csv = "ID,Name,Category,Price,Discount Price,Stock Quantity,Freshness Status,Date Received,Expiry Date,Supplier\n";
        
        foreach ($flowers as $flower) {
            $csv .= sprintf(
                "%d,\"%s\",\"%s\",%.2f,%.2f,%d,\"%s\",%s,%s,\"%s\"\n",
                $flower->getId(),
                str_replace('"', '""', $flower->getName()),
                str_replace('"', '""', $flower->getCategory()),
                $flower->getPrice(),
                $flower->getDiscountPrice() ?? 0,
                $flower->getStockQuantity(),
                str_replace('"', '""', $flower->getFreshnessStatus() ?? ''),
                $flower->getDateReceived() ? $flower->getDateReceived()->format('Y-m-d') : '',
                $flower->getExpiryDate() ? $flower->getExpiryDate()->format('Y-m-d') : '',
                $flower->getSupplier() ? str_replace('"', '""', $flower->getSupplier()->getSupplierName()) : ''
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="inventory_report_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    #[Route('/top-sellers', name: 'app_reports_top_sellers')]
    public function topSellersReport(ReservationDetailRepository $detailRepo): Response
    {
        $results = $detailRepo->createQueryBuilder('rd')
            ->select('IDENTITY(rd.flower) as flowerId, f.name, f.category, SUM(rd.quantity) as totalSold, SUM(rd.subtotal) as totalRevenue')
            ->join('rd.flower', 'f')
            ->groupBy('rd.flower, f.name, f.category')
            ->orderBy('totalSold', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        return $this->render('reports/top_sellers.html.twig', [
            'top_sellers' => $results,
        ]);
    }

    #[Route('/export/customers-csv', name: 'app_reports_export_customers_csv')]
    public function exportCustomersCsv(
        CustomerRepository $customerRepo,
        ReservationRepository $reservationRepo
    ): Response {
        $customers = $customerRepo->findAll();

        $csv = "ID,Full Name,Email,Phone,Address,Date Registered,Total Orders,Total Spent\n";

        foreach ($customers as $customer) {
            $reservations = $reservationRepo->findBy(['customer' => $customer]);
            $totalSpent = array_reduce($reservations, fn($sum, $r) => $sum + $r->getTotalAmount(), 0);

            $csv .= sprintf(
                "%d,\"%s\",\"%s\",\"%s\",\"%s\",%s,%d,%.2f\n",
                $customer->getId(),
                str_replace('"', '""', $customer->getFullName()),
                str_replace('"', '""', $customer->getEmail()),
                str_replace('"', '""', $customer->getPhone()),
                str_replace('"', '""', $customer->getAddress() ?? ''),
                $customer->getDateRegistered() ? $customer->getDateRegistered()->format('Y-m-d') : '',
                count($reservations),
                $totalSpent
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="customers_report_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    #[Route('/sold-flower-history', name: 'app_reports_sold_flower_history')]
    public function soldFlowerHistory(
        FlowerRepository $flowerRepo,
        ReservationDetailRepository $detailRepo,
        ReservationRepository $reservationRepo
    ): Response {
        // Get all sold-out / unavailable flowers
        $soldFlowers = $flowerRepo->createQueryBuilder('f')
            ->where('f.status = :soldOut OR f.status = :unavailable OR f.stockQuantity <= 0')
            ->setParameter('soldOut', 'Sold Out')
            ->setParameter('unavailable', 'Unavailable')
            ->orderBy('f.soldAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Per-flower sales summary from ReservationDetail
        $salesData = $detailRepo->createQueryBuilder('rd')
            ->select(
                'IDENTITY(rd.flower) as flowerId',
                'SUM(rd.quantity) as totalUnitsSold',
                'SUM(rd.subtotal) as totalRevenue',
                'COUNT(DISTINCT IDENTITY(rd.reservation)) as totalOrders'
            )
            ->groupBy('rd.flower')
            ->getQuery()
            ->getResult();

        $salesMap = [];
        foreach ($salesData as $row) {
            $salesMap[$row['flowerId']] = $row;
        }

        // Recent individual sale line-items for the detail table
        $recentSales = $detailRepo->createQueryBuilder('rd')
            ->select('rd.id', 'rd.quantity', 'rd.subtotal',
                     'f.name as flowerName', 'f.category as flowerCategory',
                     'r.dateReserved', 'r.reservationStatus',
                     'c.fullName as customerName')
            ->join('rd.flower', 'f')
            ->join('rd.reservation', 'r')
            ->leftJoin('r.customer', 'c')
            ->orderBy('r.dateReserved', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        // Summary stats
        $totalSoldFlowers = count($soldFlowers);
        $totalUnitsSold = array_sum(array_column($salesData, 'totalUnitsSold'));
        $totalRevenue = array_sum(array_column($salesData, 'totalRevenue'));

        return $this->render('reports/sold_flower_history.html.twig', [
            'soldFlowers' => $soldFlowers,
            'salesMap' => $salesMap,
            'recentSales' => $recentSales,
            'totalSoldFlowers' => $totalSoldFlowers,
            'totalUnitsSold' => $totalUnitsSold,
            'totalRevenue' => $totalRevenue,
        ]);
    }

    #[Route('/export/suppliers-csv', name: 'app_reports_export_suppliers_csv')]
    public function exportSuppliersCsv(
        SupplierRepository $supplierRepo,
        FlowerRepository $flowerRepo
    ): Response {
        $suppliers = $supplierRepo->findAll();

        $csv = "ID,Supplier Name,Contact Person,Phone,Email,Address,Delivery Schedule,Flower Count,Total Stock,Inventory Value\n";

        foreach ($suppliers as $supplier) {
            $flowers = $flowerRepo->findBy(['supplier' => $supplier]);
            $totalStock = array_reduce($flowers, fn($sum, $f) => $sum + $f->getStockQuantity(), 0);
            $totalValue = array_reduce($flowers, function($sum, $f) {
                $price = $f->getDiscountPrice() > 0 ? $f->getDiscountPrice() : $f->getPrice();
                return $sum + ($price * $f->getStockQuantity());
            }, 0);

            $csv .= sprintf(
                "%d,\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",%d,%d,%.2f\n",
                $supplier->getId(),
                str_replace('"', '""', $supplier->getSupplierName()),
                str_replace('"', '""', $supplier->getContactPerson()),
                str_replace('"', '""', $supplier->getPhone()),
                str_replace('"', '""', $supplier->getEmail()),
                str_replace('"', '""', $supplier->getAddress() ?? ''),
                str_replace('"', '""', $supplier->getDeliverySchedule() ?? ''),
                count($flowers),
                $totalStock,
                $totalValue
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="suppliers_report_' . date('Y-m-d') . '.csv"');

        return $response;
    }
}
