<?php

namespace App\Controller;

use App\Entity\Flower;
use App\Form\FlowerType;
use App\Repository\FlowerRepository;
use App\Service\ActivityLogService;
use App\Service\FlowerStatusUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/flower')]
#[IsGranted('ROLE_STAFF')]
final class FlowerController extends AbstractController
{
    #[Route(name: 'app_flower_index', methods: ['GET'])]
    public function index(FlowerRepository $flowerRepository): Response
    {
        return $this->render('flower/index.html.twig', [
            'flowers' => $flowerRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_flower_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, FlowerStatusUpdater $flowerStatusUpdater, ActivityLogService $activityLog): Response
    {
        $flower = new Flower();
        
        // Set default values
        $flower->setDateReceived(new \DateTime());
        $flower->setFreshnessStatus('Fresh'); // Will be updated automatically
        
        $form = $this->createForm(FlowerType::class, $flower);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($flower);
            $entityManager->flush();
            
            // Log activity
            $activityLog->logCreate('Flower', $flower->getId(), $flower->getName());
            
            // Update freshness status after saving
            $flowerStatusUpdater->updateFlowerStatuses();

            $this->addFlash('success', 'Flower created successfully!');
            return $this->redirectToRoute('app_flower_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('flower/new.html.twig', [
            'flower' => $flower,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_flower_show', methods: ['GET'])]
    public function show(Flower $flower): Response
    {
        return $this->render('flower/show.html.twig', [
            'flower' => $flower,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_flower_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Flower $flower, EntityManagerInterface $entityManager, FlowerStatusUpdater $flowerStatusUpdater, ActivityLogService $activityLog): Response
    {
        $form = $this->createForm(FlowerType::class, $flower);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            
            // Log activity
            $activityLog->logUpdate('Flower', $flower->getId(), $flower->getName());
            
            // Update freshness status after editing
            $flowerStatusUpdater->updateFlowerStatuses();

            $this->addFlash('success', 'Flower updated successfully!');
            return $this->redirectToRoute('app_flower_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('flower/edit.html.twig', [
            'flower' => $flower,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_flower_delete', methods: ['POST'])]
    public function delete(Request $request, Flower $flower, EntityManagerInterface $entityManager, ActivityLogService $activityLog): Response
    {
        if ($this->isCsrfTokenValid('delete'.$flower->getId(), $request->getPayload()->getString('_token'))) {
            $flowerName = $flower->getName();
            $flowerId = $flower->getId();
            
            $entityManager->remove($flower);
            $entityManager->flush();
            
            // Log activity
            $activityLog->logDelete('Flower', $flowerId, $flowerName);
            
            $this->addFlash('success', 'Flower deleted successfully!');
        }

        return $this->redirectToRoute('app_flower_index', [], Response::HTTP_SEE_OTHER);
    }
}
