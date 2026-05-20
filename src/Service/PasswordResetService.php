<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordResetService
{
    private const TOKEN_TTL_HOURS = 1;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private MailerInterface $mailer,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Create a reset token and email it to the customer. Returns false when no
     * matching customer account exists (caller should still respond generically).
     */
    public function requestReset(string $email): bool
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return false;
        }

        $user = $this->userRepository->findOneByEmail($normalized);
        if ($user === null || !$user->isCustomer()) {
            return false;
        }

        $token = $this->generateToken();
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(
            (new \DateTimeImmutable())->modify('+' . self::TOKEN_TTL_HOURS . ' hours')
        );
        $this->entityManager->flush();

        $this->sendResetEmail($user, $token);

        return true;
    }

    public function sendResetEmail(User $user, string $token): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address('gyeoptorres@gmail.com', 'Floryn Garden System'))
            ->to(new Address((string) $user->getEmail()))
            ->subject('Reset your Floryn Garden password')
            ->htmlTemplate('emails/password_reset.html.twig')
            ->context([
                'user'  => $user,
                'token' => $token,
            ]);

        $this->mailer->send($email);
    }

    /**
     * @throws \InvalidArgumentException when token is invalid or expired
     */
    public function resetPassword(string $token, string $plainPassword): User
    {
        $token = trim($token);
        if ($token === '') {
            throw new \InvalidArgumentException('Reset token is required.');
        }

        if (\strlen($plainPassword) < 6) {
            throw new \InvalidArgumentException('Password must be at least 6 characters.');
        }

        $user = $this->userRepository->findOneBy(['resetToken' => $token]);
        if ($user === null) {
            throw new \InvalidArgumentException('Invalid or expired reset token.');
        }

        $expiresAt = $user->getResetTokenExpiresAt();
        if ($expiresAt === null || $expiresAt < new \DateTime()) {
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            $this->entityManager->flush();
            throw new \InvalidArgumentException('Invalid or expired reset token.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);
        $this->entityManager->flush();

        return $user;
    }
}
