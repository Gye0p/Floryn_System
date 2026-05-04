<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api', name: 'api_')]
class ApiEmailVerificationController extends AbstractController
{
    public function __construct(
        private EmailVerificationService $emailVerificationService,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Verify email address using a token sent in the verification email.
     *
     * POST /api/verify-email
     * Body: { "token": "<64-char hex token>" }
     *
     * Public — no authentication required.
     */
    #[Route('/verify-email', name: 'verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;

        if (!$token) {
            return $this->json([
                'success' => false,
                'message' => 'Verification token is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->emailVerificationService->verifyToken($token);

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid or expired verification token.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success'  => true,
            'message'  => 'Email verified successfully.',
            'user'     => [
                'id'         => $user->getId(),
                'email'      => $user->getEmail(),
                'isVerified' => $user->isVerified(),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Resend the verification email to the currently authenticated user.
     *
     * POST /api/resend-verification
     * Requires a valid JWT — the user must be logged in but not yet verified.
     */
    #[Route('/resend-verification', name: 'resend_verification', methods: ['POST'])]
    public function resendVerification(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isVerified()) {
            return $this->json([
                'success' => false,
                'message' => 'Your email is already verified.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Generate a fresh token and persist it.
        $token = $this->emailVerificationService->generateVerificationToken();
        $user->setVerificationToken($token);
        $this->entityManager->flush();

        // Build the web verification URL (email link opens a browser page).
        $verificationUrl = $this->generateUrl(
            'app_verify_email',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);

        return $this->json([
            'success' => true,
            'message' => 'Verification email resent successfully.',
        ], Response::HTTP_OK);
    }

    /**
     * Return the verification status for the currently authenticated user.
     *
     * GET /api/verification-status
     * Requires a valid JWT.
     */
    #[Route('/verification-status', name: 'verification_status', methods: ['GET'])]
    public function verificationStatus(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'success'    => true,
            'isVerified' => $user->isVerified(),
            'email'      => $user->getEmail(),
        ], Response::HTTP_OK);
    }
}
