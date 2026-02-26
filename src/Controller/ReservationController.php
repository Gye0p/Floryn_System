<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\FlowerBatch;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use App\Repository\FlowerBatchRepository;
use App\Service\ActivityLogService;
use App\Service\EmailNotificationService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservation')]
#[IsGranted('ROLE_STAFF')]
final class ReservationController extends AbstractController
{
    #[Route(name: 'app_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservationRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_reservation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActivityLogService $activityLog, EmailNotificationService $emailNotification): Response
    {
        $reservation = new Reservation();
        $reservation->setDateReserved(new \DateTime());
        $reservation->setPaymentStatus('Unpaid');
        $reservation->setReservationStatus('Pending');
        
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->beginTransaction();
            try {
            // Validate stock availability before proceeding
            $stockErrors = [];
            foreach ($reservation->getReservationDetails() as $detail) {
                $flower = $detail->getFlower();
                // Lock flower row to prevent concurrent overselling
                $entityManager->lock($flower, LockMode::PESSIMISTIC_WRITE);
                $entityManager->refresh($flower);
                if ($flower->getStockQuantity() < $detail->getQuantity()) {
                    $stockErrors[] = sprintf(
                        'Insufficient stock for "%s": requested %d, available %d.',
                        $flower->getName(),
                        $detail->getQuantity(),
                        $flower->getStockQuantity()
                    );
                }
            }

            if (!empty($stockErrors)) {
                $entityManager->rollback();
                foreach ($stockErrors as $error) {
                    $this->addFlash('danger', $error);
                }
                return $this->render('reservation/new.html.twig', [
                    'reservation' => $reservation,
                    'form' => $form,
                ]);
            }

            // Calculate total amount from reservation details
            $totalAmount = 0;
            foreach ($reservation->getReservationDetails() as $detail) {
                $flower = $detail->getFlower();
                $price = $flower->getDiscountPrice() > 0 ? $flower->getDiscountPrice() : $flower->getPrice();
                $subtotal = $price * $detail->getQuantity();
                $detail->setSubtotal($subtotal);
                $detail->setReservation($reservation);
                $totalAmount += $subtotal;
                
                // Update flower stock — use FIFO batch deduction if batches exist
                $batchRepo = $entityManager->getRepository(FlowerBatch::class);
                if (!$flower->getBatches()->isEmpty()) {
                    $batchRepo->deductStock($flower, $detail->getQuantity());
                } else {
                    $newStock = $flower->getStockQuantity() - $detail->getQuantity();
                    $flower->setStockQuantity($newStock);
                }

                // Mark as Sold Out if no stock remaining
                if ($flower->getStockQuantity() <= 0) {
                    $flower->setStatus('Sold Out');
                }
            }
            
            $reservation->setTotalAmount($totalAmount);
            
            $entityManager->persist($reservation);
            $entityManager->flush();
            $entityManager->commit();

            $activityLog->logCreate('Reservation', $reservation->getId(), 'Reservation #' . $reservation->getId() . ' for ' . $reservation->getCustomer()->getFullName());
            
            // Send reservation confirmation email
            $emailNotification->sendReservationConfirmation($reservation);
            
            $this->addFlash('success', 'Reservation created successfully! Total: ₱' . number_format($totalAmount, 2));

            return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                $entityManager->rollback();
                $this->addFlash('danger', 'An error occurred while creating the reservation. Please try again.');
                return $this->render('reservation/new.html.twig', [
                    'reservation' => $reservation,
                    'form' => $form,
                ]);
            }
        }

        return $this->render('reservation/new.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation): Response
    {
        return $this->render('reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reservation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reservation $reservation, EntityManagerInterface $entityManager, ActivityLogService $activityLog): Response
    {
        $originalDetails = [];
        foreach ($reservation->getReservationDetails() as $detail) {
            $originalDetails[$detail->getId()] = [
                'flower' => $detail->getFlower(),
                'quantity' => $detail->getQuantity()
            ];
        }
        
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->beginTransaction();
            try {
            // Restore stock for ALL original details first
            $batchRepo = $entityManager->getRepository(FlowerBatch::class);
            foreach ($originalDetails as $detailId => $original) {
                if (!$original['flower']->getBatches()->isEmpty()) {
                    $batchRepo->restoreStock($original['flower'], $original['quantity']);
                } else {
                    $original['flower']->setStockQuantity(
                        $original['flower']->getStockQuantity() + $original['quantity']
                    );
                }
            }

            // Validate stock availability with restored quantities
            $stockErrors = [];
            foreach ($reservation->getReservationDetails() as $detail) {
                $flower = $detail->getFlower();
                if ($flower->getStockQuantity() < $detail->getQuantity()) {
                    $stockErrors[] = sprintf(
                        'Insufficient stock for "%s": requested %d, available %d.',
                        $flower->getName(),
                        $detail->getQuantity(),
                        $flower->getStockQuantity()
                    );
                }
            }

            if (!empty($stockErrors)) {
                // Rollback: re-deduct the restored stock
                foreach ($originalDetails as $detailId => $original) {
                    if (!$original['flower']->getBatches()->isEmpty()) {
                        $batchRepo->deductStock($original['flower'], $original['quantity']);
                    } else {
                        $original['flower']->setStockQuantity(
                            $original['flower']->getStockQuantity() - $original['quantity']
                        );
                    }
                }
                foreach ($stockErrors as $error) {
                    $this->addFlash('danger', $error);
                }
                return $this->render('reservation/edit.html.twig', [
                    'reservation' => $reservation,
                    'form' => $form,
                ]);
            }

            // Calculate new total and update stock
            $totalAmount = 0;
            foreach ($reservation->getReservationDetails() as $detail) {
                $flower = $detail->getFlower();
                $price = $flower->getDiscountPrice() > 0 ? $flower->getDiscountPrice() : $flower->getPrice();
                $subtotal = $price * $detail->getQuantity();
                $detail->setSubtotal($subtotal);
                $totalAmount += $subtotal;
                
                // Deduct new quantities from stock
                if (!$flower->getBatches()->isEmpty()) {
                    $batchRepo->deductStock($flower, $detail->getQuantity());
                } else {
                    $newStock = $flower->getStockQuantity() - $detail->getQuantity();
                    $flower->setStockQuantity($newStock);
                }

                // Mark as Sold Out if no stock remaining
                if ($flower->getStockQuantity() <= 0) {
                    $flower->setStatus('Sold Out');
                }
            }
            
            $reservation->setTotalAmount($totalAmount);
            $entityManager->flush();
            $entityManager->commit();

            $activityLog->logUpdate('Reservation', $reservation->getId(), 'Reservation #' . $reservation->getId());
            $this->addFlash('success', 'Reservation updated successfully! New Total: ₱' . number_format($totalAmount, 2));

            return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                $entityManager->rollback();
                $this->addFlash('danger', 'An error occurred while updating the reservation. Please try again.');
                return $this->render('reservation/edit.html.twig', [
                    'reservation' => $reservation,
                    'form' => $form,
                ]);
            }
        }

        return $this->render('reservation/edit.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_delete', methods: ['POST'])]
    public function delete(Request $request, Reservation $reservation, EntityManagerInterface $entityManager, ActivityLogService $activityLog): Response
    {
        if ($this->isCsrfTokenValid('delete'.$reservation->getId(), $request->getPayload()->getString('_token'))) {
            $reservationId = $reservation->getId();
            
            // Restore stock quantities before deleting
            $batchRepo = $entityManager->getRepository(FlowerBatch::class);
            foreach ($reservation->getReservationDetails() as $detail) {
                $flower = $detail->getFlower();
                if (!$flower->getBatches()->isEmpty()) {
                    $batchRepo->restoreStock($flower, $detail->getQuantity());
                } else {
                    $flower->setStockQuantity(
                        $flower->getStockQuantity() + $detail->getQuantity()
                    );
                }
                // Restore availability if stock was replenished
                if ($flower->getStockQuantity() > 0 && $flower->getStatus() === 'Sold Out') {
                    $flower->setStatus('Available');
                }
            }
            
            $entityManager->remove($reservation);
            $entityManager->flush();
            
            $activityLog->logDelete('Reservation', $reservationId, 'Reservation #' . $reservationId);
            $this->addFlash('success', 'Reservation deleted successfully!');
        }

        return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
    }
}
