<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\CustomerType;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/customer')]
#[IsGranted('ROLE_STAFF')]
final class CustomerController extends AbstractController
{
    #[Route(name: 'app_customer_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('customer/index.html.twig', [
            'customers' => $userRepository->findCustomers(),
        ]);
    }

    #[Route('/new', name: 'app_customer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActivityLogService $activityLog): Response
    {
        $customer = new User();
        $customer->setRoles(['ROLE_CUSTOMER']);
        $customer->setIsApproved(true);
        $customer->setCreatedAt(new \DateTime());
        // Walk-in / staff-created customers get a non-loginable password
        $customer->setPassword('__no_password__');

        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Generate a username from fullName if not set
            if (!$customer->getUsername()) {
                $customer->setUsername('customer_' . time() . '_' . random_int(100, 999));
            }
            $entityManager->persist($customer);
            $entityManager->flush();

            $activityLog->logCreate('Customer', $customer->getId(), $customer->getFullName());
            $this->addFlash('success', 'Customer created successfully!');

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/new.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_customer_show', methods: ['GET'])]
    public function show(User $customer): Response
    {
        return $this->render('customer/show.html.twig', [
            'customer' => $customer,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_customer_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $customer, EntityManagerInterface $entityManager, ActivityLogService $activityLog): Response
    {
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $activityLog->logUpdate('Customer', $customer->getId(), $customer->getFullName());
            $this->addFlash('success', 'Customer updated successfully!');

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/edit.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_customer_delete', methods: ['POST'])]
    public function delete(Request $request, User $customer, EntityManagerInterface $entityManager, ActivityLogService $activityLog): Response
    {
        if ($this->isCsrfTokenValid('delete'.$customer->getId(), $request->getPayload()->getString('_token'))) {
            $customerName = $customer->getFullName();
            $customerId = $customer->getId();
            
            $entityManager->remove($customer);
            $entityManager->flush();
            
            $activityLog->logDelete('Customer', $customerId, $customerName);
            $this->addFlash('success', 'Customer deleted successfully!');
        }

        return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
    }
}
