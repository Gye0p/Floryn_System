<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Form\PaymentType;
use App\Repository\PaymentRepository;
use App\Service\ActivityLogService;
use App\Service\EmailNotificationService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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
        $payment->setPaymentDate(new \DateTime());

        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reservation = $payment->getReservation();
            if (!$reservation) {
                $this->addFlash('danger', 'Please select an unpaid reservation.');
                return $this->render('payment/new.html.twig', [
                    'payment' => $payment,
                    'form' => $form,
                ]);
            }

            if ($reservation->getPayment() !== null) {
                $this->addFlash('danger', sprintf(
                    'Reservation #%d already has a payment recorded. Edit or delete that payment first.',
                    $reservation->getId()
                ));
                return $this->render('payment/new.html.twig', [
                    'payment' => $payment,
                    'form' => $form,
                ]);
            }

            // Always record the full reservation total (avoids mismatch rejections)
            $payment->setAmountPaid($reservation->getTotalAmount());
            $payment->setStatus('Paid');

            $reservation->setPayment($payment);
            $reservation->setPaymentStatus('Paid');

            try {
                $entityManager->persist($payment);
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('danger', sprintf(
                    'Reservation #%d already has a payment in the database.',
                    $reservation->getId()
                ));
                return $this->render('payment/new.html.twig', [
                    'payment' => $payment,
                    'form' => $form,
                ]);
            }

            $activityLog->logCreate('Payment', $payment->getId(), 'Payment #' . $payment->getId());
            $emailNotification->sendPaymentConfirmation($payment);

            $this->addFlash('success', 'Payment recorded successfully! Reservation #' . $reservation->getId() . ' marked as Paid.');

            return $this->redirectToRoute('app_payment_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted()) {
            $this->addFlash('danger', $this->formatPaymentFormErrors($form));
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

        if ($form->isSubmitted()) {
            $this->addFlash('danger', $this->formatPaymentFormErrors($form));
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

            $reservation = $payment->getReservation();
            if ($reservation) {
                $reservation->setPaymentStatus('Unpaid');
                $reservation->setPayment(null);
            }

            $entityManager->remove($payment);
            $entityManager->flush();

            $activityLog->logDelete('Payment', $paymentId, 'Payment #' . $paymentId);
            $this->addFlash('success', 'Payment deleted successfully! Reservation reverted to Unpaid.');
        }

        return $this->redirectToRoute('app_payment_index', [], Response::HTTP_SEE_OTHER);
    }

    private function formatPaymentFormErrors(\Symfony\Component\Form\FormInterface $form): string
    {
        $messages = [];

        foreach ($form->getErrors(true) as $error) {
            $messages[] = $error->getMessage();
        }

        foreach ($form as $child) {
            foreach ($child->getErrors() as $error) {
                $label = ucfirst($child->getName());
                $messages[] = $label . ': ' . $error->getMessage();
            }
        }

        if ($messages === []) {
            return 'Could not save payment. Check reservation, payment date, method, and reference number.';
        }

        return 'Could not save payment — ' . implode(' ', array_unique($messages));
    }
}
