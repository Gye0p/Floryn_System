<?php

namespace App\Controller;

use App\Entity\Bouquet;
use App\Repository\BouquetRepository;
use App\Repository\FlowerRepository;
use App\Service\ActivityLogService;
use App\Service\BouquetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/bouquet')]
#[IsGranted('ROLE_STAFF')]
class BouquetController extends AbstractController
{
    #[Route('', name: 'app_bouquet_index', methods: ['GET'])]
    public function index(BouquetRepository $bouquetRepository): Response
    {
        return $this->render('bouquet/index.html.twig', [
            'bouquets' => $bouquetRepository->findAllWithItems(),
        ]);
    }

    /**
     * Create a new bouquet (name + optional description/notes).
     * Returns the new bouquet ID so the frontend can immediately redirect to builder.
     */
    #[Route('/new', name: 'app_bouquet_new', methods: ['POST'])]
    public function create(
        Request $request,
        BouquetService $bouquetService,
        EntityManagerInterface $em,
        ActivityLogService $activityLog
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('bouquet_new', $request->getPayload()->getString('_token'))) {
            return $this->json(['success' => false, 'error' => 'Invalid security token.'], 403);
        }

        $name = trim($request->getPayload()->getString('name'));
        if ($name === '') {
            return $this->json(['success' => false, 'error' => 'Bouquet name is required.'], 400);
        }

        $bouquet = $bouquetService->createBouquet(
            $name,
            trim($request->getPayload()->getString('description')) ?: null,
            trim($request->getPayload()->getString('notes')) ?: null
        );

        $em->flush();

        return $this->json([
            'success' => true,
            'id' => $bouquet->getId(),
            'message' => sprintf('Bouquet "%s" created.', $bouquet->getName()),
        ]);
    }

    /**
     * Bouquet builder — view / manage a single bouquet.
     */
    #[Route('/{id}', name: 'app_bouquet_show', methods: ['GET'])]
    public function show(Bouquet $bouquet, FlowerRepository $flowerRepository): Response
    {
        $flowers = $flowerRepository->createQueryBuilder('f')
            ->where('f.stockQuantity > 0')
            ->andWhere('f.status != :soldOut')
            ->andWhere('f.status != :unavailable')
            ->setParameter('soldOut', 'Sold Out')
            ->setParameter('unavailable', 'Unavailable')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('bouquet/show.html.twig', [
            'bouquet' => $bouquet,
            'flowers' => $flowers,
        ]);
    }

    /**
     * Add a flower to an existing bouquet — deducts stock immediately.
     */
    #[Route('/{id}/add-flower', name: 'app_bouquet_add_flower', methods: ['POST'])]
    public function addFlower(
        Bouquet $bouquet,
        Request $request,
        BouquetService $bouquetService,
        FlowerRepository $flowerRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('bouquet_add_flower', $request->getPayload()->getString('_token'))) {
            return $this->json(['success' => false, 'error' => 'Invalid security token.'], 403);
        }

        if (in_array($bouquet->getStatus(), ['Sold', 'Cancelled'], true)) {
            return $this->json(['success' => false, 'error' => 'Cannot modify a ' . $bouquet->getStatus() . ' bouquet.'], 400);
        }

        $flowerId = (int) $request->getPayload()->getString('flowerId');
        $quantity = (int) $request->getPayload()->getString('quantity');

        $flower = $flowerRepository->find($flowerId);
        if (!$flower) {
            return $this->json(['success' => false, 'error' => 'Flower not found.'], 404);
        }

        $em->beginTransaction();
        try {
            $item = $bouquetService->addFlowerToBouquet($bouquet, $flower, $quantity);
            $em->flush();
            $em->commit();

            return $this->json([
                'success' => true,
                'itemId' => $item->getId(),
                'flowerName' => $flower->getName(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $item->getUnitPrice(),
                'subtotal' => $item->getSubtotal(),
                'totalPrice' => $bouquet->getTotalPrice(),
                'stockRemaining' => $flower->getStockQuantity(),
            ]);
        } catch (\InvalidArgumentException $e) {
            $em->rollback();

            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $em->rollback();

            return $this->json(['success' => false, 'error' => 'Could not add flower to bouquet.'], 500);
        }
    }

    /**
     * Remove a flower item from a bouquet — restores the stock.
     */
    #[Route('/{id}/remove-item/{itemId}', name: 'app_bouquet_remove_item', methods: ['DELETE', 'POST'])]
    public function removeItem(
        Bouquet $bouquet,
        int $itemId,
        Request $request,
        BouquetService $bouquetService,
        EntityManagerInterface $em
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('bouquet_remove_item', $request->getPayload()->getString('_token'))) {
            return $this->json(['success' => false, 'error' => 'Invalid security token.'], 403);
        }

        if (in_array($bouquet->getStatus(), ['Sold', 'Cancelled'], true)) {
            return $this->json(['success' => false, 'error' => 'Cannot modify a ' . $bouquet->getStatus() . ' bouquet.'], 400);
        }

        $item = null;
        foreach ($bouquet->getItems() as $i) {
            if ($i->getId() === $itemId) {
                $item = $i;
                break;
            }
        }

        if (!$item) {
            return $this->json(['success' => false, 'error' => 'Item not found in this bouquet.'], 404);
        }

        $em->beginTransaction();
        try {
            $bouquetService->removeItemFromBouquet($bouquet, $item);
            $em->flush();
            $em->commit();
        } catch (\Throwable $e) {
            $em->rollback();

            return $this->json(['success' => false, 'error' => 'Could not remove item from bouquet.'], 500);
        }

        return $this->json([
            'success' => true,
            'totalPrice' => $bouquet->getTotalPrice(),
        ]);
    }

    /**
     * Mark bouquet as Ready.
     */
    #[Route('/{id}/mark-ready', name: 'app_bouquet_mark_ready', methods: ['POST'])]
    public function markReady(
        Bouquet $bouquet,
        Request $request,
        BouquetService $bouquetService,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('bouquet_status_' . $bouquet->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_bouquet_show', ['id' => $bouquet->getId()]);
        }

        if ($bouquet->getItems()->isEmpty()) {
            $this->addFlash('error', 'Cannot mark an empty bouquet as Ready.');
            return $this->redirectToRoute('app_bouquet_show', ['id' => $bouquet->getId()]);
        }

        $bouquetService->markReady($bouquet);
        $em->flush();

        $this->addFlash('success', sprintf('Bouquet "%s" is now Ready.', $bouquet->getName()));
        return $this->redirectToRoute('app_bouquet_show', ['id' => $bouquet->getId()]);
    }

    /**
     * Mark bouquet as Sold.
     */
    #[Route('/{id}/mark-sold', name: 'app_bouquet_mark_sold', methods: ['POST'])]
    public function markSold(
        Bouquet $bouquet,
        Request $request,
        BouquetService $bouquetService,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('bouquet_status_' . $bouquet->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_bouquet_show', ['id' => $bouquet->getId()]);
        }

        $bouquetService->markSold($bouquet);
        $em->flush();

        $this->addFlash('success', sprintf('Bouquet "%s" marked as Sold.', $bouquet->getName()));
        return $this->redirectToRoute('app_bouquet_index');
    }

    /**
     * Cancel bouquet — restores all stock.
     */
    #[Route('/{id}/cancel', name: 'app_bouquet_cancel', methods: ['POST'])]
    public function cancel(
        Bouquet $bouquet,
        Request $request,
        BouquetService $bouquetService,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('bouquet_status_' . $bouquet->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_bouquet_show', ['id' => $bouquet->getId()]);
        }

        $bouquetService->cancelBouquet($bouquet);
        $em->flush();

        $this->addFlash('success', sprintf('Bouquet "%s" cancelled. Stock has been restored.', $bouquet->getName()));
        return $this->redirectToRoute('app_bouquet_index');
    }
}
