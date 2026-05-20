<?php

namespace App\Controller;

use App\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/password-reset', name: 'api_password_reset_')]
class ApiPasswordResetController extends AbstractController
{
    /**
     * POST /api/password-reset/request
     * Body: { "email": "user@example.com" }
     */
    #[Route('/request', name: 'request', methods: ['POST'])]
    public function request(
        Request $request,
        PasswordResetService $passwordResetService,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = isset($data['email']) ? trim((string) $data['email']) : '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(
                ['error' => 'A valid email address is required.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Always return the same message to avoid email enumeration.
        $passwordResetService->requestReset($email);

        return $this->json([
            'message' => 'If an account exists for that email, a password reset code has been sent.',
        ]);
    }

    /**
     * POST /api/password-reset/confirm
     * Body: { "token": "...", "password": "newpass" }
     */
    #[Route('/confirm', name: 'confirm', methods: ['POST'])]
    public function confirm(
        Request $request,
        PasswordResetService $passwordResetService,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $token = isset($data['token']) ? trim((string) $data['token']) : '';
        $password = isset($data['password']) ? (string) $data['password'] : '';

        if ($token === '' || $password === '') {
            return $this->json(
                ['error' => 'Token and new password are required.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $passwordResetService->resetPassword($token, $password);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'message' => 'Your password has been reset. You can now sign in.',
        ]);
    }
}
