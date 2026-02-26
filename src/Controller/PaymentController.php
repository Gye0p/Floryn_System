<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Form\PaymentType;
use App\Repository\PaymentRepository;
use App\Service\ActivityLogService;
use App\Service\EmailNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/payment')]
#[IsGranted('ROLE_STAFF')]
final class PaymentController extends AbstractController
{
    #[Route(name: 'app_payment_index', methods: ['GET'])]
    public function index(PaymentRepository $paymentRepository): Response
    {
        return $this->render('payment/index.html.twig', [
            'payments' => $paymentRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_payment_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActivityLogService $activityLog, EmailNotificationService $emailNotification): Response
    {
        $payment = new Payment();
        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reservation = $payment->getReservation();

            // Enforce full payment: amount must equal the reservation total
            if ($reservation && abs($payment->getAmountPaid() - $reservation->getTotalAmount()) > 0.01) {
                $this->addFlash('danger', sprintf(
                    'Full payment required. The reservation total is ₱%s but you entered ₱%s.',
                    number_format($reservation->getTotalAmount(), 2),
                    number_format($payment->getAmountPaid(), 2)
                ));
                return $this->render('payment/new.html.twig', [
                    'payment' => $payment,
                    'form' => $form,
                ]);
            }

            // Auto-set payment status to Paid
            $payment->setStatus('Paid');

            $entityManager->persist($payment);

            // Sync reservation payment status
            if ($reservation) {
                $reservation->setPaymentStatus('Paid');
            }

            $entityManager->flush();

            $activityLog->logCreate('Payment', $payment->getId(), 'Payment #' . $payment->getId());
            
            // Send payment confirmation email
            $emailNotification->sendPaymentConfirmation($payment);
            
            $this->addFlash('success', 'Payment recorded successfully! Reservation #' . $reservation->getId() . ' marked as Paid.');

            return $this->redirectToRoute('app_payment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('payment/new.html.twig', [
            'payment' => $payment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_payment_show', methods: ['GET'])]
    public function show(Payment $payment): Response
    {
        return $this->render('payment/show.html.twig', [
            'payment' => $payment,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_payment_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Payment $payment, EntityManagerInterface $entityManager, ActivityLogService $activityLog): Response
    {
        $form = $this->createForm(PaymentType::class, $payment, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sync reservation payment status based on payment status
            $reservation = $payment->getReservation();
            if ($reservation) {
                if ($payment->getStatus() === 'Cancelled') {
                    $reservation->setPaymentStatus('Unpaid');
                } else {
                    $reservation->setPaymentStatus('Paid');
                }
            }

            $entityManager->flush();

            $activityLog->logUpdate('Payment', $payment->getId(), 'Payment #' . $payment->getId());
            $this->addFlash('success', 'Payment updated successfully!');

            return $this->redirectToRoute('app_payment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('payment/edit.html.twig', [
            'payment' => $payment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_payment_delete', methods: ['POST'])]
    public function delete(Request $request, Payment $payment, EntityManagerInterface $entityManager, ActivityLogService $activityLog): Response
    {
        if ($this->isCsrfTokenValid('delete'.$payment->getId(), $request->getPayload()->getString('_token'))) {
            $paymentId = $payment->getId();
            
            // Revert reservation payment status to Unpaid
            $reservation = $payment->getReservation();
            if ($reservation) {
                $reservation->setPaymentStatus('Unpaid');
            }
            
            $entityManager->remove($payment);
            $entityManager->flush();
            
            $activityLog->logDelete('Payment', $paymentId, 'Payment #' . $paymentId);
            $this->addFlash('success', 'Payment deleted successfully! Reservation reverted to Unpaid.');
        }

        return $this->redirectToRoute('app_payment_index', [], Response::HTTP_SEE_OTHER);
    }
}
