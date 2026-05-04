<?php

namespace App\Controller;

use App\Service\EmailVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmailVerificationController extends AbstractController
{
    public function __construct(
        private EmailVerificationService $emailVerificationService,
    ) {}


    #[Route('/verify-email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request): Response
    {
        $token = $request->query->get('token');

        if (!$token) {
            $this->addFlash('error', 'No verification token provided.');

            return $this->redirectToRoute('app_login');
        }

        $user = $this->emailVerificationService->verifyToken($token);

        if (!$user) {
            $this->addFlash('error', 'Invalid or expired verification link. Please request a new one.');

            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('success', 'Your email has been verified! You can now log in.');

        return $this->redirectToRoute('app_login');
    }
}
