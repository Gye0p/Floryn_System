<?php

namespace App\Service;

use App\Entity\NotificationLog;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends Firebase Cloud Messaging (FCM) push notifications to mobile app users.
 * Uses FCM HTTP v1 API authenticated via a service account JSON key.
 */
class FcmNotificationService
{
    private const FCM_ENDPOINT = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const CREDENTIALS_PATH = __DIR__ . '/../../config/firebase-credentials.json';

    private ?string $accessToken = null;
    private ?int $tokenExpiresAt = null;
    private ?string $projectId = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Send a push notification when a reservation status changes.
     */
    public function sendReservationStatusUpdate(User $user, Reservation $reservation): void
    {
        if (!$user->getFcmToken()) {
            $this->logger->info('FCM: skipping — user has no FCM token', [
                'user_id' => $user->getId(),
            ]);
            return;
        }

        $status = $reservation->getReservationStatus();

        $title = match ($status) {
            'Confirmed'  => '✅ Reservation Confirmed!',
            'Completed'  => '🌸 Order Completed!',
            'Cancelled'  => '❌ Reservation Cancelled',
            default      => '📋 Reservation Update',
        };

        $body = match ($status) {
            'Confirmed'  => "Your reservation #{$reservation->getId()} has been confirmed. Pickup: {$reservation->getPickupDate()?->format('M d, Y')}",
            'Completed'  => "Your flower order #{$reservation->getId()} has been completed. Thank you!",
            'Cancelled'  => "Your reservation #{$reservation->getId()} has been cancelled.",
            default      => "Your reservation #{$reservation->getId()} status changed to {$status}.",
        };

        $this->send($user->getFcmToken(), $title, $body, [
            'type'           => 'reservation_update',
            'reservation_id' => (string) $reservation->getId(),
            'status'         => $status,
        ]);

        $this->logNotification($user, $reservation, "FCM: {$title} — {$body}");
    }

    /**
     * Send a push notification when payment status changes.
     */
    public function sendPaymentStatusUpdate(User $user, Reservation $reservation): void
    {
        if (!$user->getFcmToken()) {
            return;
        }

        $paymentStatus = $reservation->getPaymentStatus();

        $title = $paymentStatus === 'Paid' ? '💳 Payment Received!' : '💳 Payment Update';
        $body  = "Payment for reservation #{$reservation->getId()} is now: {$paymentStatus}.";

        $this->send($user->getFcmToken(), $title, $body, [
            'type'           => 'payment_update',
            'reservation_id' => (string) $reservation->getId(),
            'payment_status' => $paymentStatus,
        ]);

        $this->logNotification($user, $reservation, "FCM: {$title} — {$body}");
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function send(string $fcmToken, string $title, string $body, array $data = []): void
    {
        try {
            $token   = $this->getAccessToken();
            $url     = sprintf(self::FCM_ENDPOINT, $this->getProjectId());

            $payload = [
                'message' => [
                    'token'        => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    'data'         => array_map('strval', $data),
                    'android'      => [
                        'notification' => [
                            'sound'        => 'default',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ],
                    ],
                ],
            ];

            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 200) {
                $this->logger->info('FCM: notification sent successfully', [
                    'title' => $title,
                ]);
            } else {
                $this->logger->error('FCM: unexpected response', [
                    'status'   => $statusCode,
                    'response' => $response->getContent(false),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('FCM: failed to send notification', [
                'error' => $e->getMessage(),
                'title' => $title,
            ]);
        }
    }

    private function getAccessToken(): string
    {
        // Return cached token if still valid (5-min buffer)
        if ($this->accessToken && $this->tokenExpiresAt && time() < ($this->tokenExpiresAt - 300)) {
            return $this->accessToken;
        }

        $credentials = $this->loadCredentials();
        if ($credentials === null) {
            throw new \RuntimeException('Firebase credentials are not configured.');
        }
        $now         = time();

        // Build JWT assertion
        $header  = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64url(json_encode([
            'iss'   => $credentials['client_email'],
            'scope' => self::FCM_SCOPE,
            'aud'   => self::TOKEN_ENDPOINT,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $data = "{$header}.{$payload}";
        openssl_sign($data, $signature, $credentials['private_key'], 'SHA256');
        $jwt = "{$data}." . $this->base64url($signature);

        $response = $this->httpClient->request('POST', self::TOKEN_ENDPOINT, [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ],
        ]);

        $result = $response->toArray();

        $this->accessToken   = $result['access_token'];
        $this->tokenExpiresAt = $now + ($result['expires_in'] ?? 3600);

        return $this->accessToken;
    }

    private function getProjectId(): string
    {
        if ($this->projectId !== null) {
            return $this->projectId;
        }

        $credentials = $this->loadCredentials();
        if ($credentials === null) {
            throw new \RuntimeException('Firebase credentials are not configured.');
        }

        $this->projectId = $credentials['project_id'];

        return $this->projectId;
    }

    /**
     * @return array<string, mixed>|null Null when credentials file is absent (push disabled).
     */
    private function loadCredentials(): ?array
    {
        $path = self::CREDENTIALS_PATH;

        if (!file_exists($path)) {
            $this->logger->warning('FCM: credentials file not found — push notifications disabled', [
                'path' => $path,
            ]);

            return null;
        }

        $credentials = json_decode(file_get_contents($path), true);

        if (!$credentials || !isset($credentials['private_key'], $credentials['project_id'], $credentials['client_email'])) {
            $this->logger->error('FCM: credentials JSON is invalid or incomplete');

            return null;
        }

        return $credentials;
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function logNotification(User $user, Reservation $reservation, string $message): void
    {
        try {
            $log = new NotificationLog();
            $log->setCustomer($user);
            $log->setReservation($reservation);
            $log->setMessage($message);
            $log->setChannel('fcm');
            $log->setStatus('sent');
            $log->setDateSent(new \DateTime());

            $this->em->persist($log);
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logger->warning('FCM: failed to log notification', ['error' => $e->getMessage()]);
        }
    }
}
