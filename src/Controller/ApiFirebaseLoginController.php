<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
class ApiFirebaseLoginController extends AbstractController
{
    /**
     * POST /api/firebase-login
     * Body: { "firebase_token": "eyJ..." }
     *
     * Verifies a Firebase ID token against Google's public tokeninfo endpoint,
     * then issues a Symfony JWT for the matching approved user account.
     *
     * Used by the mobile app for Google Sign-In, where no password is available
     * to call the standard /api/login endpoint.
     */
    #[Route('/firebase-login', name: 'firebase_login', methods: ['POST'])]
    public function firebaseLogin(
        Request $request,
        UserRepository $userRepository,
        JWTTokenManagerInterface $jwtManager,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $firebaseToken = $data['firebase_token'] ?? null;

        if (empty($firebaseToken)) {
            return $this->json(
                ['error' => 'firebase_token is required.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // ── Verify with Google tokeninfo ──────────────────────────────────────
        // Google will reject expired or tampered tokens, returning an error key.
        $url     = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($firebaseToken);
        $context = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
        $raw     = file_get_contents($url, false, $context);

        if ($raw === false) {
            return $this->json(
                ['error' => 'Could not reach token verification service. Try again.'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        $tokenInfo = json_decode($raw, true);

        if (!empty($tokenInfo['error'])) {
            return $this->json(
                ['error' => 'Invalid or expired Firebase token.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // ── Extract email from the verified token ─────────────────────────────
        $email = $tokenInfo['email'] ?? null;
        if (empty($email)) {
            return $this->json(
                ['error' => 'Email not found in Firebase token payload.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // ── Look up the user in Symfony's database ────────────────────────────
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->json([
                'error'  => 'No Floryn account found for this Google account.',
                'hint'   => 'Please register with this email first.',
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$user->isApproved()) {
            return $this->json([
                'error'  => 'Your account is pending admin approval. Please wait for confirmation.',
                'status' => 'pending',
            ], Response::HTTP_FORBIDDEN);
        }

        // ── Issue a Symfony JWT ───────────────────────────────────────────────
        $token = $jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'user'  => [
                'id'       => $user->getId(),
                'username' => $user->getUsername(),
                'fullName' => $user->getFullName(),
                'email'    => $user->getEmail(),
                'roles'    => $user->getRoles(),
            ],
        ]);
    }
}
