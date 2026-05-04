<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserManagementController extends AbstractController
{
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user_management/index.html.twig', [
            'users' => $userRepository->findApprovedUsers(),
        ]);
    }

    #[Route('/pending', name: 'app_user_pending', methods: ['GET'])]
    public function pending(UserRepository $userRepository): Response
    {
        return $this->render('user_management/pending.html.twig', [
            'pendingUsers' => $userRepository->findPendingUsers(),
        ]);
    }

    #[Route('/{id}/approve', name: 'app_user_approve', methods: ['POST'])]
    public function approve(Request $request, User $user, EntityManagerInterface $entityManager, ActivityLogService $activityLog): Response
    {
        if ($this->isCsrfTokenValid('approve'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $user->setIsApproved(true);
            $entityManager->flush();
            $activityLog->logUpdate('User', $user->getId(), $user->getUsername() . ' (Account Approved)');
            $this->addFlash('success', 'Account for "' . $user->getUsername() . '" has been approved. They can now log in.');
        }

        return $this->redirectToRoute('app_user_pending', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/reject', name: 'app_user_reject', methods: ['POST'])]
    public function reject(Request $request, User $user, EntityManagerInterface $entityManager, ActivityLogService $activityLog): Response
    {
        if ($this->isCsrfTokenValid('reject'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $userId   = $user->getId();
            $username = $user->getUsername();
            $entityManager->remove($user);
            $entityManager->flush();
            $activityLog->logDelete('User', $userId, $username . ' (Registration Rejected)');
            $this->addFlash('warning', 'Registration for "' . $username . '" has been rejected and removed.');
        }

        return $this->redirectToRoute('app_user_pending', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, ActivityLogService $activityLog): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the plain password
            $plainPassword = $form->get('plainPassword')->getData();
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            // Admin-created users are auto-approved
            $user->setIsApproved(true);
            $user->setCreatedAt(new \DateTime());

            $entityManager->persist($user);
            $entityManager->flush();

            // Log the action
            $activityLog->logCreate('User', $user->getId(), $user->getUsername());

            $this->addFlash('success', 'User created successfully!');

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user_management/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user_management/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, ActivityLogService $activityLog): Response
    {
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the plain password if provided
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            $entityManager->flush();

            // Log the action
            $activityLog->logUpdate('User', $user->getId(), $user->getUsername());

            $this->addFlash('success', 'User updated successfully!');

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user_management/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager, ActivityLogService $activityLog): Response
    {
        // Prevent admin from deleting themselves
        if ($user->getId() === $this->getUser()?->getId()) {
            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        if (!$this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        // Capture user info before deleting
        $userId = $user->getId();
        $username = $user->getUsername();

        try {
            $entityManager->remove($user);
            $entityManager->flush();

            $activityLog->logDelete('User', $userId, $username);
            $this->addFlash('success', 'User "' . $username . '" deleted successfully!');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Cannot delete user "' . $username . '". They may have associated records (reservations or orders) that must be removed first.');
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
