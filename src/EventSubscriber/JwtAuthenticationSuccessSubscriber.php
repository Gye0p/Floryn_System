<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\RefreshTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JwtAuthenticationSuccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokenService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            AuthenticationSuccessEvent::class => 'onAuthenticationSuccess',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $data = $event->getData();
        $data = array_merge($data, $this->refreshTokenService->createForUser($user));
        $event->setData($data);
    }
}
