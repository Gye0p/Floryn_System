<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class GoogleController extends AbstractController
{
    /**
     * Redirect the user to Google for authentication.
     * Link this to the "Sign in with Google" button.
     */
    #[Route('/connect/google', name: 'connect_google_start')]
    public function connectAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        // Redirect to Google with required scopes
        return $clientRegistry
            ->getClient('google')
            ->redirect([
                'email',
                'profile',
            ], []);
    }

    /**
     * Google callback URL — this is called after the user authenticates with Google.
     * The GoogleAuthenticator handles the actual authentication logic.
     */
    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheckAction(Request $request): void
    {
        // This method is never actually called.
        // The GoogleAuthenticator intercepts this route and handles authentication.
    }
}
