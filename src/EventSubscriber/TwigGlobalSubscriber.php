<?php

namespace App\EventSubscriber;

use App\Repository\NotificationLogRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

/**
 * Injects global template variables (e.g. unread notification count)
 * into every Twig render so that the topbar can show the notification badge.
 */
class TwigGlobalSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Environment $twig,
        private NotificationLogRepository $notificationLogRepository,
        private TokenStorageInterface $tokenStorage,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onController',
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Only inject for authenticated users (avoids queries on login page, etc.)
        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            return;
        }

        $this->twig->addGlobal(
            'unread_notification_count',
            $this->notificationLogRepository->countPending()
        );
    }
}
