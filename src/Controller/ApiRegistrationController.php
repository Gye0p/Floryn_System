<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailNotificationService;
use App\Service\GoogleIdTokenVerifier;
use App\Service\GoogleTokenVerificationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_')]
class ApiRegistrationController extends AbstractController
{
    /**
     * Customer registration for the Floryn mobile app (email + password).
     *
     * POST /api/register
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        ValidatorInterface $validator,
        EmailNotificationService $emailNotifier,
        EventDispatcherInterface $eventDispatcher,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $requiredFields = ['username', 'password', 'email', 'full_name'];
        $missing = [];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            return $this->json([
                'error'   => 'Missing required fields.',
                'missing' => $missing,
            ], Response::HTTP_BAD_REQUEST);
        }

        $email = strtolower(trim($data['email']));
        $username = trim($data['username']);

        if ($userRepository->findOneByEmail($email)) {
            return $this->json([
                'error' => 'An account with this email already exists. Please sign in instead.',
            ], Response::HTTP_CONFLICT);
        }

        $existingUser = $userRepository->findOneBy(['username' => $username]);
        if ($existingUser) {
            return $this->json([
                'error' => 'Username is already taken.',
            ], Response::HTTP_CONFLICT);
        }

        $user = $this->buildCustomerUser(
            $username,
            $email,
            trim($data['full_name']),
            $this->normalizeOptionalString($data['phone'] ?? null),
            $this->normalizeOptionalString($data['address'] ?? null),
        );

        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $validationError = $this->validateUser($user, $validator);
        if ($validationError !== null) {
            return $validationError;
        }

        $entityManager->persist($user);
        $entityManager->flush();

        $this->scheduleRegistrationEmail(
            $eventDispatcher,
            $emailNotifier,
            $user->getEmail(),
            $user->getFullName() ?? $username,
        );

        return $this->registrationSuccessResponse($user);
    }

    /**
     * Google Sign-In registration for the mobile app.
     *
     * POST /api/register/google
     * Body: { "firebase_token": "...", "full_name": "optional", "phone": "", "address": "" }
     */
    #[Route('/register/google', name: 'register_google', methods: ['POST'])]
    public function registerGoogle(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        ValidatorInterface $validator,
        GoogleIdTokenVerifier $tokenVerifier,
        EmailNotificationService $emailNotifier,
        EventDispatcherInterface $eventDispatcher,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $idToken = $data['firebase_token'] ?? null;

        if (empty($idToken)) {
            return $this->json(
                ['error' => 'firebase_token is required.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $claims = $tokenVerifier->verify($idToken);
        } catch (GoogleTokenVerificationException $e) {
            $payload = ['error' => $e->getMessage()];
            if ($e->getDetail() !== null) {
                $payload['detail'] = $e->getDetail();
            }

            return $this->json($payload, $e->getStatusCode());
        }

        $email = $claims['email'];

        $existing = $userRepository->findForGoogleSignIn($claims['googleId'], $email);
        if ($existing !== null) {
            if ($existing->isApproved()) {
                return $this->json([
                    'error' => 'An account with this email already exists. Please sign in instead.',
                ], Response::HTTP_CONFLICT);
            }

            return $this->json([
                'error'  => 'An account with this email is already registered and pending admin approval.',
                'status' => 'pending',
            ], Response::HTTP_CONFLICT);
        }

        $username = $this->deriveUsername($email, $data['full_name'] ?? $claims['name'], $userRepository);
        $fullName = trim($data['full_name'] ?? $claims['name'] ?? $username);

        $user = $this->buildCustomerUser(
            $username,
            $email,
            $fullName,
            $this->normalizeOptionalString($data['phone'] ?? null),
            $this->normalizeOptionalString($data['address'] ?? null),
        );

        if ($claims['googleId'] !== null) {
            $user->setGoogleId($claims['googleId']);
        }

        // Random password — Google users authenticate via /api/firebase-login.
        $randomPassword = bin2hex(random_bytes(32));
        $user->setPassword($passwordHasher->hashPassword($user, $randomPassword));

        $validationError = $this->validateUser($user, $validator);
        if ($validationError !== null) {
            return $validationError;
        }

        $entityManager->persist($user);
        $entityManager->flush();

        $this->scheduleRegistrationEmail(
            $eventDispatcher,
            $emailNotifier,
            $user->getEmail(),
            $user->getFullName() ?? $username,
        );

        return $this->registrationSuccessResponse($user);
    }

    /**
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
            return $this->json(['error' => 'Username is required.'], Response::HTTP_BAD_REQUEST);
        }

        $identifier = trim($data['username']);

        $user = $userRepository->findOneBy(['username' => $identifier])
            ?? $userRepository->findOneByEmail($identifier);

        if (!$user) {
            return $this->json(['error' => 'Account not found.'], Response::HTTP_NOT_FOUND);
        }

        $approved = $user->isApproved();

        return $this->json([
            'username'    => $user->getUsername(),
            'is_approved' => $approved,
            'approved'    => $approved,
            'isApproved'  => $approved,
            'message'     => $approved
                ? 'Your account has been approved. You can now log in.'
                : 'Your account is still pending approval. Please wait for an admin to approve it.',
        ]);
    }

    private function normalizeOptionalString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Send the pending-approval email after the HTTP response so mobile clients
     * are not left waiting on slow SMTP (avoids 15s axios "Network Error").
     */
    private function scheduleRegistrationEmail(
        EventDispatcherInterface $eventDispatcher,
        EmailNotificationService $emailNotifier,
        ?string $userEmail,
        string $fullName,
    ): void {
        if ($userEmail === null || $userEmail === '') {
            return;
        }

        $eventDispatcher->addListener(
            KernelEvents::TERMINATE,
            static function () use ($emailNotifier, $userEmail, $fullName): void {
                $emailNotifier->sendRegistrationPendingEmail($userEmail, $fullName);
            },
        );
    }

    private function buildCustomerUser(
        string $username,
        string $email,
        string $fullName,
        ?string $phone,
        ?string $address,
    ): User {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setFullName($fullName);
        $user->setPhone($phone);
        $user->setAddress($address);
        $user->setRoles(['ROLE_CUSTOMER']);
        $user->setIsApproved(false);
        $user->setIsVerified(true);
        $user->setCreatedAt(new \DateTime());

        return $user;
    }

    private function validateUser(User $user, ValidatorInterface $validator): ?JsonResponse
    {
        $errors = $validator->validate($user);
        if (count($errors) === 0) {
            return null;
        }

        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[$error->getPropertyPath()] = $error->getMessage();
        }

        return $this->json([
            'error'   => 'Validation failed.',
            'details' => $errorMessages,
        ], Response::HTTP_BAD_REQUEST);
    }

    private function registrationSuccessResponse(User $user): JsonResponse
    {
        return $this->json([
            'message' => 'Registration successful! Your account is pending admin approval. You will be notified once it is approved.',
            'user'    => [
                'id'          => $user->getId(),
                'username'    => $user->getUsername(),
                'email'       => $user->getEmail(),
                'role'        => 'ROLE_CUSTOMER',
                'is_approved' => false,
                'is_verified' => true,
            ],
        ], Response::HTTP_CREATED);
    }

    private function deriveUsername(string $email, ?string $fullName, UserRepository $userRepository): string
    {
        $candidates = [];

        $localPart = explode('@', $email)[0] ?? '';
        if ($localPart !== '') {
            $candidates[] = preg_replace('/[^a-z0-9_.-]/i', '', $localPart) ?? $localPart;
        }

        if ($fullName) {
            $fromName = strtolower(preg_replace('/\s+/', '_', trim($fullName)) ?? '');
            $fromName = preg_replace('/[^a-z0-9_.-]/', '', $fromName) ?? '';
            if ($fromName !== '') {
                $candidates[] = $fromName;
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            if ($userRepository->findOneBy(['username' => $candidate]) === null) {
                return $candidate;
            }
        }

        $base = $candidates[0] ?? 'user';
        for ($i = 1; $i <= 99; ++$i) {
            $attempt = $base . $i;
            if ($userRepository->findOneBy(['username' => $attempt]) === null) {
                return $attempt;
            }
        }

        return $base . bin2hex(random_bytes(3));
    }
}
