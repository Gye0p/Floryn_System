<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\GoogleIdTokenVerifier;
use App\Service\GoogleTokenVerificationException;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\RefreshTokenService;
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
     * Verifies a Google OAuth2 ID token, then issues a Symfony JWT for the
     * matching approved customer account (lookup by googleId, then email).
     */
    #[Route('/firebase-login', name: 'firebase_login', methods: ['POST'])]
    public function firebaseLogin(
        Request $request,
        UserRepository $userRepository,
        JWTTokenManagerInterface $jwtManager,
        GoogleIdTokenVerifier $tokenVerifier,
        EntityManagerInterface $entityManager,
        RefreshTokenService $refreshTokenService,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $idToken = $data['firebase_token'] ?? null;

        if (empty($idToken)) {
            return $this->json(
                ['error' => 'firebase_token is required.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $claims = $tokenVerifier->verify($idToken);
        } catch (GoogleTokenVerificationException $e) {
            $payload = ['error' => $e->getMessage()];
            if ($e->getDetail() !== null) {
                $payload['detail'] = $e->getDetail();
            }

            return $this->json($payload, $e->getStatusCode());
        }

        $user = $userRepository->findForGoogleSignIn($claims['googleId'], $claims['email']);

        if (!$user) {
            return $this->json([
                'error' => 'No Floryn account found for this Google account.',
                'hint'  => 'Please register with this email first.',
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$user->isCustomer()) {
            return $this->json([
                'error' => 'This Google account is not linked to a customer account.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Link Google ID to an email/password account created via /api/register.
        $linked = false;
        if ($claims['googleId'] !== null && $user->getGoogleId() !== $claims['googleId']) {
            $user->setGoogleId($claims['googleId']);
            $linked = true;
        }
        if (!$user->isVerified()) {
            $user->setIsVerified(true);
            $linked = true;
        }
        if ($linked) {
            $entityManager->flush();
        }

        if (!$user->isApproved()) {
            return $this->json([
                'error'  => 'Your account is pending admin approval. Please wait for confirmation.',
                'status' => 'pending',
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json(array_merge(
            [
                'token' => $jwtManager->create($user),
                'user'  => [
                    'id'       => $user->getId(),
                    'username' => $user->getUsername(),
                    'fullName' => $user->getFullName(),
                    'email'    => $user->getEmail(),
                    'roles'    => $user->getRoles(),
                ],
            ],
            $refreshTokenService->createForUser($user),
        ));
    }
}
