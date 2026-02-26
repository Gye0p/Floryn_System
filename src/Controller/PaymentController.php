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
            $entityManager->persist($payment);
            $entityManager->flush();

            $activityLog->logCreate('Payment', $payment->getId(), 'Payment #' . $payment->getId());
            
            // Send payment confirmation email
            $emailNotification->sendPaymentConfirmation($payment);
            
            $this->addFlash('success', 'Payment recorded successfully!');

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
        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
            
            $entityManager->remove($payment);
            $entityManager->flush();
            
            $activityLog->logDelete('Payment', $paymentId, 'Payment #' . $paymentId);
            $this->addFlash('success', 'Payment deleted successfully!');
        }

        return $this->redirectToRoute('app_payment_index', [], Response::HTTP_SEE_OTHER);
    }
}
