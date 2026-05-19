<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies Google OAuth2 ID tokens from the mobile app (Google Sign-In).
 * Uses Google's public tokeninfo endpoints — same flow as the web OAuth client.
 */
class GoogleIdTokenVerifier
{
    /**
     * @return array{email: string, googleId: string|null, name: string|null}
     *
     * @throws GoogleTokenVerificationException
     */
    public function verify(string $idToken): array
    {
        $idToken = trim($idToken);
        if ($idToken === '') {
            throw new GoogleTokenVerificationException(
                'firebase_token is required.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $context = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);

        $tokenInfo = null;
        foreach ([
            'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken),
            'https://www.googleapis.com/oauth2/v3/tokeninfo?id_token=' . urlencode($idToken),
        ] as $url) {
            $raw = @file_get_contents($url, false, $context);
            if ($raw === false) {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (!empty($decoded['email'])) {
                $tokenInfo = $decoded;
                break;
            }

            if ($tokenInfo === null) {
                $tokenInfo = $decoded;
            }
        }

        if ($tokenInfo === null) {
            throw new GoogleTokenVerificationException(
                'Could not reach token verification service. Try again.',
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        if (!empty($tokenInfo['error']) || empty($tokenInfo['email'])) {
            $detail = $tokenInfo['error_description'] ?? $tokenInfo['error'] ?? 'unknown';
            throw new GoogleTokenVerificationException(
                'Invalid or expired Google token.',
                Response::HTTP_UNAUTHORIZED,
                $detail
            );
        }

        return [
            'email'     => strtolower(trim($tokenInfo['email'])),
            'googleId'  => isset($tokenInfo['sub']) ? (string) $tokenInfo['sub'] : null,
            'name'      => isset($tokenInfo['name']) ? trim((string) $tokenInfo['name']) : null,
        ];
    }
}

class GoogleTokenVerificationException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly ?string $detail = null,
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }
}
