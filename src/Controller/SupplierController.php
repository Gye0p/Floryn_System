<?php

namespace App\Controller;

use App\Entity\Supplier;
use App\Form\SupplierType;
use App\Repository\SupplierRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/supplier')]
#[IsGranted('ROLE_STAFF')]
final class SupplierController extends AbstractController
{
    #[Route(name: 'app_supplier_index', methods: ['GET'])]
    public function index(SupplierRepository $supplierRepository): Response
    {
        return $this->render('supplier/index.html.twig', [
            'suppliers' => $supplierRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_supplier_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActivityLogService $activityLog): Response
    {
        $supplier = new Supplier();
        $form = $this->createForm(SupplierType::class, $supplier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($supplier);
            $entityManager->flush();

            $activityLog->logCreate('Supplier', $supplier->getId(), $supplier->getSupplierName());
            $this->addFlash('success', 'Supplier created successfully!');

            return $this->redirectToRoute('app_supplier_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('supplier/new.html.twig', [
            'supplier' => $supplier,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_supplier_show', methods: ['GET'])]
    public function show(Supplier $supplier): Response
    {
        return $this->render('supplier/show.html.twig', [
            'supplier' => $supplier,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_supplier_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Supplier $supplier, EntityManagerInterface $entityManager, ActivityLogService $activityLog): Response
    {
        $form = $this->createForm(SupplierType::class, $supplier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $activityLog->logUpdate('Supplier', $supplier->getId(), $supplier->getSupplierName());
            $this->addFlash('success', 'Supplier updated successfully!');

            return $this->redirectToRoute('app_supplier_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('supplier/edit.html.twig', [
            'supplier' => $supplier,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_supplier_delete', methods: ['POST'])]
    public function delete(Request $request, Supplier $supplier, EntityManagerInterface $entityManager, ActivityLogService $activityLog): Response
    {
        if ($this->isCsrfTokenValid('delete'.$supplier->getId(), $request->getPayload()->getString('_token'))) {
            $supplierName = $supplier->getSupplierName();
            $supplierId = $supplier->getId();
            
            $entityManager->remove($supplier);
            $entityManager->flush();
            
            $activityLog->logDelete('Supplier', $supplierId, $supplierName);
            $this->addFlash('success', 'Supplier deleted successfully!');
        }

        return $this->redirectToRoute('app_supplier_index', [], Response::HTTP_SEE_OTHER);
    }
}
