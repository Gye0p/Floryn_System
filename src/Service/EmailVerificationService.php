<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailVerificationService
{
    public function __construct(private
        EntityManagerInterface $entityManager, private
        MailerInterface $mailer
        )
    {
    }

    /**
     * Generate a cryptographically secure 64-character hex token.
     */
    public function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Send the HTML verification email via Brevo SMTP.
     */
    public function sendVerificationEmail(User $user, string $verificationUrl): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address('gyeoptorres@gmail.com', 'Floryn Garden System'))
            ->to(new Address((string)$user->getEmail()))
            ->subject('Please verify your email address — Floryn Garden')
            ->htmlTemplate('emails/verification.html.twig')
            ->context([
                'user' => $user,
                'verificationUrl' => $verificationUrl,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Look up user by token, mark as verified, clear token, flush to DB.
     * Returns the User on success, or null if token is invalid.
     */
    public function verifyToken(string $token): ?User
    {
        $user = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            return null;
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null); // invalidate — single-use token
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Convenience helper — true when the user has not yet verified their email.
     */
    public function needsVerification(User $user): bool
    {
        return !$user->isVerified();
    }
}
