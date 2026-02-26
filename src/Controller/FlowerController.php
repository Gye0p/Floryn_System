<?php

namespace App\Controller;

use App\Entity\Flower;
use App\Entity\FlowerBatch;
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
        // Active flowers: in stock and not sold out / unavailable
        $activeFlowers = $flowerRepository->createQueryBuilder('f')
            ->where('f.stockQuantity > 0')
            ->andWhere('f.status != :soldOut')
            ->andWhere('f.status != :unavailable')
            ->setParameter('soldOut', 'Sold Out')
            ->setParameter('unavailable', 'Unavailable')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Sold out / unavailable flowers (0 stock OR marked sold/unavailable)
        $soldFlowers = $flowerRepository->createQueryBuilder('f')
            ->where('f.stockQuantity <= 0')
            ->orWhere('f.status = :soldOut')
            ->orWhere('f.status = :unavailable')
            ->setParameter('soldOut', 'Sold Out')
            ->setParameter('unavailable', 'Unavailable')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('flower/index.html.twig', [
            'flowers' => $activeFlowers,
            'soldFlowers' => $soldFlowers,
        ]);
    }

    #[Route('/new', name: 'app_flower_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, FlowerStatusUpdater $flowerStatusUpdater, ActivityLogService $activityLog): Response
    {
        $flower = new Flower();
        
        // Set default values — status is system-managed, always Available for new flowers
        $flower->setDateReceived(new \DateTime());
        $flower->setStatus('Available');
        $flower->setFreshnessStatus('Fresh'); // Will be recalculated by FlowerStatusUpdater
        
        $form = $this->createForm(FlowerType::class, $flower);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Create an initial batch for the new flower
            $batch = new FlowerBatch();
            $batch->setFlower($flower);
            $batch->setQuantityReceived($flower->getStockQuantity());
            $batch->setQuantityRemaining($flower->getStockQuantity());
            $batch->setDateReceived($flower->getDateReceived() ?? new \DateTime());
            $batch->setExpiryDate($flower->getExpiryDate());
            $batch->setActive(true);
            $flower->addBatch($batch);

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

    #[Route('/{id}/restock', name: 'app_flower_restock', methods: ['POST'])]
    public function restock(Request $request, Flower $flower, EntityManagerInterface $entityManager, FlowerStatusUpdater $flowerStatusUpdater, ActivityLogService $activityLog): Response
    {
        if (!$this->isCsrfTokenValid('restock' . $flower->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('app_flower_index');
        }

        $newStock = (int) $request->request->get('stock_quantity', 0);
        $newExpiryDate = $request->request->get('expiry_date');

        if ($newStock <= 0) {
            $this->addFlash('danger', 'Stock quantity must be greater than zero.');
            return $this->redirectToRoute('app_flower_show', ['id' => $flower->getId()]);
        }

        if (empty($newExpiryDate)) {
            $this->addFlash('danger', 'Expiry date is required for restocking.');
            return $this->redirectToRoute('app_flower_show', ['id' => $flower->getId()]);
        }

        try {
            $expiryDate = new \DateTime($newExpiryDate);
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Invalid expiry date format.');
            return $this->redirectToRoute('app_flower_show', ['id' => $flower->getId()]);
        }
        $today = new \DateTime('today');
        if ($expiryDate < $today) {
            $this->addFlash('danger', 'Expiry date must be today or in the future.');
            return $this->redirectToRoute('app_flower_show', ['id' => $flower->getId()]);
        }

        // Create a new batch for this restock delivery
        $batch = new FlowerBatch();
        $batch->setFlower($flower);
        $batch->setQuantityReceived($newStock);
        $batch->setQuantityRemaining($newStock);
        $batch->setDateReceived(new \DateTime());
        $batch->setExpiryDate($expiryDate);
        $batch->setActive(true);
        $flower->addBatch($batch);

        // Sync flower summary fields from all active batches
        $flower->syncFromBatches();
        $flower->setStatus('Available');
        $flower->setSoldAt(null);

        $entityManager->persist($batch);
        $entityManager->flush();

        // Update freshness status
        $flowerStatusUpdater->updateFlowerStatuses();

        $activityLog->logUpdate('Flower', $flower->getId(), 'Restocked ' . $flower->getName() . ' with ' . $newStock . ' units');
        $this->addFlash('success', sprintf('"%s" has been restocked with %d units!', $flower->getName(), $newStock));

        return $this->redirectToRoute('app_flower_index', [], Response::HTTP_SEE_OTHER);
    }
}
