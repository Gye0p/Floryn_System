<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Enriches the JWT login response with user profile data.
 * The mobile app receives user info immediately on login without a second /api/me call.
 */
class JwtEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'lexik_jwt_authentication.on_authentication_success' => 'onAuthenticationSuccess',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $data = $event->getData();

        $data['user'] = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'phone' => $user->getPhone(),
            'address' => $user->getAddress(),
            'roles' => $user->getRoles(),
            'isApproved' => $user->isApproved(),
            'isVerified' => $user->isVerified(),
        ];

        $event->setData($data);
    }
}
