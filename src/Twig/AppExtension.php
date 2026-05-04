<?php

namespace App\Twig;

use App\Repository\UserRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_users_count', [$this, 'getPendingUsersCount']),
        ];
    }

    public function getPendingUsersCount(): int
    {
        return $this->userRepository->countPendingUsers();
    }
}
