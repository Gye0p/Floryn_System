<?php

namespace App\Controller;

use App\Service\RefreshTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/token', name: 'api_token_')]
class ApiTokenRefreshController extends AbstractController
{
    /**
     * POST /api/token/refresh
     * Body: { "refresh_token": "..." }
     * Returns: { "token": "...", "refresh_token": "...", "expires_at": "..." }
     */
    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(
        Request $request,
        RefreshTokenService $refreshTokenService,
        JWTTokenManagerInterface $jwtManager,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $plain = isset($data['refresh_token']) ? trim((string) $data['refresh_token']) : '';

        if ($plain === '') {
            return $this->json(['error' => 'refresh_token is required.'], Response::HTTP_BAD_REQUEST);
        }

        $stored = $refreshTokenService->findValid($plain);
        if ($stored === null) {
            return $this->json(['error' => 'Invalid or expired refresh token.'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $stored->getUser();
        if ($user === null) {
            return $this->json(['error' => 'Invalid refresh token.'], Response::HTTP_UNAUTHORIZED);
        }

        $refreshTokenService->revoke($stored);

        return $this->json(array_merge(
            ['token' => $jwtManager->create($user)],
            $refreshTokenService->createForUser($user),
        ));
    }
}
