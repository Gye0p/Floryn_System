<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;


#[Route('/api/admin/users', name: 'api_admin_users_')]
#[IsGranted('ROLE_ADMIN')]
class ApiAdminUserController extends AbstractController
{
   
    #[Route('/staff', name: 'staff_create', methods: ['POST'])]
    public function createStaff(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        ValidatorInterface $validator,
        ActivityLogService $activityLog,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $username = isset($data['username']) ? trim((string) $data['username']) : '';
        $password = isset($data['password']) ? (string) $data['password'] : '';
        $email = isset($data['email']) ? trim((string) $data['email']) : '';

        if ($username === '' || $password === '') {
            return $this->json([
                'error' => 'Missing required fields.',
                'missing' => array_values(array_filter([
                    $username === '' ? 'username' : null,
                    $password === '' ? 'password' : null,
                ])),
            ], Response::HTTP_BAD_REQUEST);
        }

        if (\strlen($username) < 3) {
            return $this->json(['error' => 'Username must be at least 3 characters.'], Response::HTTP_BAD_REQUEST);
        }

        if (\strlen($password) < 6) {
            return $this->json(['error' => 'Password must be at least 6 characters.'], Response::HTTP_BAD_REQUEST);
        }

        if ($userRepository->findOneBy(['username' => $username])) {
            return $this->json(['error' => 'Username is already taken.'], Response::HTTP_CONFLICT);
        }

        if ($email !== '' && $userRepository->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'An account with this email already exists.'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setUsername($username);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_STAFF']);
        $user->setIsApproved(true);
        $user->setIsVerified(true);
        $user->setCreatedAt(new \DateTime());

        if ($email !== '') {
            $user->setEmail($email);
        }

        $errors = $validator->validate($user);
        if (\count($errors) > 0) {
            $details = [];
            foreach ($errors as $error) {
                $details[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json(['error' => 'Validation failed.', 'details' => $details], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        $activityLog->logCreate('User', $user->getId(), $user->getUsername() . ' (API staff)');

        return $this->json([
            'message' => 'Staff user created.',
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ], Response::HTTP_CREATED);
    }
}
