<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/activity-logs')]
#[IsGranted('ROLE_ADMIN')]
class ActivityLogController extends AbstractController
{
    #[Route('/', name: 'app_activity_log_index', methods: ['GET'])]
    public function index(Request $request, ActivityLogRepository $activityLogRepository): Response
    {
        $username = $request->query->get('username');
        $action = $request->query->get('action');
        $date = null;
        $dateString = $request->query->get('date');
        if ($dateString) {
            try {
                $date = new \DateTime($dateString);
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Invalid date format.');
            }
        }

        if ($username || $action || $date) {
            $logs = $activityLogRepository->findWithFilters($username, $action, $date);
        } else {
            $logs = $activityLogRepository->findAll();
        }

        return $this->render('activity_log/index.html.twig', [
            'logs' => $logs,
            'filters' => [
                'username' => $username,
                'action' => $action,
                'date' => $date?->format('Y-m-d'),
            ],
        ]);
    }
}
