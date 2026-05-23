<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\FlowerBatch;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use App\Repository\FlowerBatchRepository;
use App\Service\ActivityLogService;
use App\Service\EmailNotificationService;
use App\Service\FcmNotificationService;
use App\Service\WebSocketNotifier;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
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
    public function new(Request $request, EntityManagerInterface $entityManager, ActivityLogService $activityLog, EmailNotificationService $emailNotification, LoggerInterface $logger): Response
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
                $price = $flower->getEffectivePrice();
                $subtotal = $price * $detail->getQuantity();
                $detail->setSubtotal($subtotal);
                $detail->setReservation($reservation);
                $totalAmount += $subtotal;
                
                // Update flower stock — use FIFO batch deduction if batches exist
                $batchRepo = $entityManager->getRepository(FlowerBatch::class);
                if ($flower->usesActiveBatchStock()) {
                    $deducted = $batchRepo->deductStock($flower, $detail->getQuantity());
                    if ($deducted < $detail->getQuantity()) {
                        throw new \RuntimeException(sprintf(
                            'Insufficient batch stock for "%s".',
                            $flower->getName()
                        ));
                    }
                } else {
                    $newStock = $flower->getStockQuantity() - $detail->getQuantity();
                    $flower->setStockQuantity($newStock);
                }

                if ($flower->getStockQuantity() <= 0) {
                    $flower->setStatus('Sold Out');
                }
            }

            $reservation->setTotalAmount($totalAmount);

            foreach ($reservation->getReservationDetails() as $detail) {
                $entityManager->persist($detail);
            }
            $entityManager->persist($reservation);
            $entityManager->flush();
            $entityManager->commit();

            $customerLabel = $reservation->getCustomer()?->getFullName()
                ?? $reservation->getCustomer()?->getUsername()
                ?? 'Customer';
            $activityLog->logCreate('Reservation', $reservation->getId(), 'Reservation #' . $reservation->getId() . ' for ' . $customerLabel);

            $emailNotification->sendReservationConfirmation($reservation);

            $this->addFlash('success', 'Reservation created successfully! Total: ₱' . number_format($totalAmount, 2));

            return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Throwable $e) {
                if ($entityManager->getConnection()->isTransactionActive()) {
                    $entityManager->rollback();
                }
                $logger->error('Reservation create failed', ['exception' => $e]);
                $this->addFlash('danger', 'Could not create reservation: ' . $e->getMessage());

                return $this->render('reservation/new.html.twig', [
                    'reservation' => $reservation,
                    'form' => $form,
                ]);
            }
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', $this->formatFormErrors($form));
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
    public function edit(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLog,
        FcmNotificationService $fcmNotification,
        WebSocketNotifier $webSocketNotifier,
    ): Response {
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
                if ($original['flower']->usesActiveBatchStock()) {
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
                    if ($original['flower']->usesActiveBatchStock()) {
                        $batchRepo->deductStock($original['flower'], $original['quantity']);
                    } else {
                        $original['flower']->setStockQuantity(
                            $original['flower']->getStockQuantity() - $original['quantity']
                        );
                    }
                }
                $entityManager->rollback();
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
                $price = $flower->getEffectivePrice();
                $subtotal = $price * $detail->getQuantity();
                $detail->setSubtotal($subtotal);
                $totalAmount += $subtotal;

                // Deduct new quantities from stock
                if ($flower->usesActiveBatchStock()) {
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

            // Notify customer via FCM push notification (works when app is closed)
            $customer = $reservation->getCustomer();
            if ($customer) {
                $fcmNotification->sendReservationStatusUpdate($customer, $reservation);
            }

            // Notify customer via WebSocket (works when app is open)
            $webSocketNotifier->broadcastReservationUpdate(
                $reservation->getId(),
                $customer?->getId() ?? 0,
                $reservation->getReservationStatus()
            );

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

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', $this->formatFormErrors($form));
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
                if ($flower->usesActiveBatchStock()) {
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

    private function formatFormErrors(FormInterface $form): string
    {
        $messages = [];
        foreach ($form->getErrors(true) as $error) {
            $messages[] = $error->getMessage();
        }
        foreach ($form as $child) {
            foreach ($child->getErrors() as $error) {
                $messages[] = ucfirst($child->getName()) . ': ' . $error->getMessage();
            }
        }

        return $messages === []
            ? 'Could not save reservation. Check all required fields.'
            : 'Could not save reservation — ' . implode(' ', array_unique($messages));
    }
}
