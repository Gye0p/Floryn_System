<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\ActivityLogService;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLog,
        EmailVerificationService $emailVerification,
        UrlGeneratorInterface $urlGenerator
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // Set default role to STAFF for self-registration
            $user->setRoles(['ROLE_STAFF']);

            // Self-registered users must wait for admin approval
            $user->setIsApproved(false);
            $user->setIsVerified(false);
            $user->setCreatedAt(new \DateTime());

            // Generate and store a verification token
            $token = $emailVerification->generateVerificationToken();
            $user->setVerificationToken($token);

            $entityManager->persist($user);
            $entityManager->flush();

            $activityLog->logCreate('User', $user->getId(), $user->getUsername() . ' (Self-Registration - Pending Approval)');

            // Build the full verification URL and send the email
            $verificationUrl = $urlGenerator->generate(
                'app_verify_email',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            try {
                $emailVerification->sendVerificationEmail($user, $verificationUrl);
            } catch (\Exception $e) {
                // Account is created; just warn that the email couldn't be sent
                $this->addFlash('warning', 'Account created but the verification email could not be sent. Please contact the administrator.');
            }

            return $this->redirectToRoute('app_registration_pending');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/register/pending', name: 'app_registration_pending')]
    public function pending(): Response
    {
        return $this->render('registration/pending.html.twig');
    }

    #[Route('/verify-email/{token}', name: 'app_verify_email')]
    public function verifyEmail(
        string $token,
        EmailVerificationService $emailVerification
    ): Response {
        $user = $emailVerification->verifyToken($token);

        if (!$user) {
            $this->addFlash('error', 'This verification link is invalid or has already been used.');
            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('success', 'Your email has been verified! Your account is pending admin approval.');
        return $this->redirectToRoute('app_login');
    }
}
