<?php

namespace App\Controller;

use App\Repository\BouquetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route(path: '/', name: 'app_home')]
    public function index(BouquetRepository $bouquetRepository): Response
    {
        // Fetch up to 8 "Ready" bouquets to showcase on the landing page
        $bouquets = $bouquetRepository->findByStatus('Ready');
        $bouquets = array_slice($bouquets, 0, 8);

        return $this->render('home/index.html.twig', [
            'bouquets' => $bouquets,
        ]);
    }
}
