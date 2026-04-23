<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\FamilyRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        FamilyRepository  $familyRepo,
        ProductRepository $productRepo,
    ): Response {
        // Familias visibles, máx. 8 (lógica de bloqueo centralizada en el repositorio)
        $families = array_slice($familyRepo->findVisible(), 0, 8);

        // Productos destacados: activos, con stock, ordenados por stock DESC
        $featuredProducts = $productRepo->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')->addSelect('f')
            ->leftJoin('p.subfamily', 's')->addSelect('s')
            ->andWhere('p.isActive = true')
            ->andWhere('p.stock >= 5')
            ->orderBy('p.stock', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        // Últimos productos añadidos
        $latestProducts = $productRepo->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')->addSelect('f')
            ->andWhere('p.isActive = true')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        // Stats para el hero
        $stats = [
            'products' => (int) $productRepo->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->andWhere('p.isActive = true')
                ->getQuery()->getSingleScalarResult(),
            'families' => count($families),
            'inStock'  => (int) $productRepo->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->andWhere('p.isActive = true')
                ->andWhere('p.stock >= 5')
                ->getQuery()->getSingleScalarResult(),
        ];

        return $this->render('home/index.html.twig', [
            'families'         => $families,
            'featuredProducts' => $featuredProducts,
            'latestProducts'   => $latestProducts,
            'stats'            => $stats,
        ]);
    }
}
