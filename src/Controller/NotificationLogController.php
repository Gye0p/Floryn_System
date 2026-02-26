<?php

namespace App\Controller;

use App\Entity\NotificationLog;
use App\Form\NotificationLogType;
use App\Repository\NotificationLogRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/notification/log')]
#[IsGranted('ROLE_STAFF')]
final class NotificationLogController extends AbstractController
{
    #[Route(name: 'app_notification_log_index', methods: ['GET'])]
    public function index(NotificationLogRepository $notificationLogRepository): Response
    {
        return $this->render('notification_log/index.html.twig', [
            'notification_logs' => $notificationLogRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_notification_log_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActivityLogService $activityLog): Response
    {
        $notificationLog = new NotificationLog();
        $form = $this->createForm(NotificationLogType::class, $notificationLog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($notificationLog);
            $entityManager->flush();

            $activityLog->logCreate('Notification', $notificationLog->getId(), 'Notification #' . $notificationLog->getId());
            $this->addFlash('success', 'Notification sent successfully!');

            return $this->redirectToRoute('app_notification_log_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('notification_log/new.html.twig', [
            'notification_log' => $notificationLog,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_notification_log_show', methods: ['GET'])]
    public function show(NotificationLog $notificationLog): Response
    {
        return $this->render('notification_log/show.html.twig', [
            'notification_log' => $notificationLog,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_notification_log_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, NotificationLog $notificationLog, EntityManagerInterface $entityManager, ActivityLogService $activityLog): Response
    {
        $form = $this->createForm(NotificationLogType::class, $notificationLog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $activityLog->logUpdate('Notification', $notificationLog->getId(), 'Notification #' . $notificationLog->getId());
            $this->addFlash('success', 'Notification updated successfully!');

            return $this->redirectToRoute('app_notification_log_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('notification_log/edit.html.twig', [
            'notification_log' => $notificationLog,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_notification_log_delete', methods: ['POST'])]
    public function delete(Request $request, NotificationLog $notificationLog, EntityManagerInterface $entityManager, ActivityLogService $activityLog): Response
    {
        if ($this->isCsrfTokenValid('delete'.$notificationLog->getId(), $request->getPayload()->getString('_token'))) {
            $notificationId = $notificationLog->getId();
            
            $entityManager->remove($notificationLog);
            $entityManager->flush();
            
            $activityLog->logDelete('Notification', $notificationId, 'Notification #' . $notificationId);
            $this->addFlash('success', 'Notification deleted successfully!');
        }

        return $this->redirectToRoute('app_notification_log_index', [], Response::HTTP_SEE_OTHER);
    }
}
