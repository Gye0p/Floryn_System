<?php

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

class RefreshTokenService
{
    private const TOKEN_BYTES = 32;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly int $ttlSeconds = 2_592_000,
    ) {}

    /**
     * @return array{refresh_token: string, expires_at: string}
     */
    public function createForUser(User $user): array
    {
        $this->refreshTokenRepository->deleteExpiredForUser($user);

        $plain = bin2hex(random_bytes(self::TOKEN_BYTES));
        $expiresAt = new \DateTimeImmutable('+' . $this->ttlSeconds . ' seconds');

        $entity = (new RefreshToken())
            ->setUser($user)
            ->setTokenHash(hash('sha256', $plain))
            ->setExpiresAt($expiresAt)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return [
            'refresh_token' => $plain,
            'expires_at' => $expiresAt->format(\DateTimeInterface::ATOM),
        ];
    }

    public function findValid(string $plainToken): ?RefreshToken
    {
        if ($plainToken === '' || !ctype_xdigit($plainToken) || strlen($plainToken) !== self::TOKEN_BYTES * 2) {
            return null;
        }

        return $this->refreshTokenRepository->findValidByHash(hash('sha256', $plainToken));
    }

    public function revoke(RefreshToken $token): void
    {
        $this->entityManager->remove($token);
        $this->entityManager->flush();
    }
}
