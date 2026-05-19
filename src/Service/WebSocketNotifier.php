<?php

namespace App\Service;

use App\Entity\Reservation;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Broadcasts real-time reservation updates via Symfony Mercure.
 * Mercure publishes Server-Sent Events (SSE) to subscribed clients.
 *
 * Topic format: /reservations/{userId}
 * This ensures each customer only receives their own updates.
 */
class WebSocketNotifier
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Broadcast a reservation status update to the customer's Mercure topic.
     */
    public function broadcastReservationUpdate(int $reservationId, int $userId, string $status): void
    {
        try {
            $topic = "/reservations/{$userId}";

            $update = new Update(
                $topic,
                json_encode([
                    'event'          => 'reservation_updated',
                    'reservation_id' => $reservationId,
                    'user_id'        => $userId,
                    'status'         => $status,
                    'timestamp'      => time(),
                ]),
                // Private: only clients with a valid JWT for this topic can subscribe
                true
            );

            $this->hub->publish($update);

            $this->logger->info('Mercure: reservation update published', [
                'topic'          => $topic,
                'reservation_id' => $reservationId,
                'status'         => $status,
            ]);
        } catch (\Throwable $e) {
            // Non-fatal: log and continue — FCM notification still works
            $this->logger->warning('Mercure: failed to publish update (is Mercure hub running?)', [
                'error'          => $e->getMessage(),
                'reservation_id' => $reservationId,
            ]);
        }
    }
}
