<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_')]
class ApiRegistrationController extends AbstractController
{
    /**
     * Customer registration endpoint for the Floryn mobile app.
     *
     * POST /api/register
     * Body: {
     *   "username": "jopchi",
     *   "password": "securepass123",
     *   "email": "jopchi@example.com",
     *   "full_name": "Jop Chi",
     *   "phone": "+639123456789",
     *   "address": "123 Flower Street"
     * }
     *
     * The account is created with ROLE_CUSTOMER and isApproved = false.
     * An admin or staff must approve the account before the user can log in.
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        ValidatorInterface $validator,
        EmailVerificationService $emailVerificationService,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'error' => 'Invalid JSON body.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate required fields (phone and address are optional)
        $requiredFields = ['username', 'password', 'email', 'full_name'];
        $missing = [];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            return $this->json([
                'error' => 'Missing required fields.',
                'missing' => $missing,
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if username already exists
        $existingUser = $userRepository->findOneBy(['username' => $data['username']]);
        if ($existingUser) {
            return $this->json([
                'error' => 'Username is already taken.',
            ], Response::HTTP_CONFLICT);
        }

        // Check if email already exists
        $existingEmail = $userRepository->findOneBy(['email' => $data['email']]);
        if ($existingEmail) {
            return $this->json([
                'error' => 'An account with this email already exists.',
            ], Response::HTTP_CONFLICT);
        }

        // Create User with ROLE_CUSTOMER
        $user = new User();
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setFullName($data['full_name']);
        $user->setPhone($data['phone'] ?? '');
        $user->setAddress($data['address'] ?? '');
        $user->setRoles(['ROLE_CUSTOMER']);
        $user->setIsApproved(false); // Requires admin/staff approval
        $user->setCreatedAt(new \DateTime());

        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        // Email verification — generate token and mark as unverified.
        $verificationToken = $emailVerificationService->generateVerificationToken();
        $user->setVerificationToken($verificationToken);
        $user->setIsVerified(false);

        // Validate User entity (phone format, email format, etc.)
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'error' => 'Validation failed.',
                'details' => $errorMessages,
            ], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        // Send verification email — wrapped in try/catch so a mail failure
        // never prevents the account from being created.
        try {
            $verificationUrl = $this->generateUrl(
                'app_verify_email',
                ['token' => $verificationToken],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $emailVerificationService->sendVerificationEmail($user, $verificationUrl);
        } catch (\Exception) {
            // Mail failure is non-fatal; user can request a resend later.
        }

        return $this->json([
            'message' => 'Registration successful! Please check your email to verify your address. Your account is also pending admin approval.',
            'user' => [
                'id'          => $user->getId(),
                'username'    => $user->getUsername(),
                'email'       => $user->getEmail(),
                'role'        => 'ROLE_CUSTOMER',
                'is_approved' => false,
                'is_verified' => false,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Check account approval status.
     *
     * POST /api/check-approval
     * Body: { "username": "jopchi" }
     */
    #[Route('/check-approval', name: 'check_approval', methods: ['POST'])]
    public function checkApproval(
        Request $request,
        UserRepository $userRepository,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data || empty($data['username'])) {
            return $this->json([
                'error' => 'Username is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['username' => $data['username']]);

        if (!$user) {
            return $this->json([
                'error' => 'Account not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'username' => $user->getUsername(),
            'is_approved' => $user->isApproved(),
            'message' => $user->isApproved()
                ? 'Your account has been approved. You can now log in.'
                : 'Your account is still pending approval. Please wait for an admin to approve it.',
        ]);
    }
}
