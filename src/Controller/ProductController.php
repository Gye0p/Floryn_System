<?php

namespace App\Controller;

use App\Repository\FlowerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ProductController extends AbstractController
{
    #[Route('/products/flowers', name: 'app_flower_products', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function flowers(FlowerRepository $flowerRepository): Response
    {
        $flowers = $flowerRepository->createQueryBuilder('f')
            ->where('f.stockQuantity > 0')
            ->andWhere('f.status = :status')
            ->setParameter('status', 'Available')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('flower/products.html.twig', [
            'flowers' => $flowers,
        ]);
    }
}
