<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
class ApiLoginController extends AbstractController
{
    /**
     * JWT Login endpoint.
     * 
     * POST /api/login
     * Body: { "username": "...", "password": "..." }
     * Returns: { "token": "eyJ..." }
     * 
     * The actual authentication is handled by the json_login firewall.
     * This method body is never reached — it exists only to define the route.
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // This controller is handled by the json_login authenticator
        // The body is never executed — Symfony intercepts /api/login
        return $this->json([
            'message' => 'Missing credentials',
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Returns the currently authenticated user's info.
     * Requires a valid JWT token in the Authorization header.
     * 
     * GET /api/me
     * Header: Authorization: Bearer <token>
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $this->json([
            'id' => $user->getId(),
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ]);
    }
}
