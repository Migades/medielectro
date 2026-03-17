<?php

namespace App\Controller;

use App\Entity\Family;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    // Familias internas que nunca deben mostrarse al cliente
    private const BLOCKED_FAMILIES = [
        'CARGO PUBLICIDAD CLIENTES',
        'MERCHANDISING',
        'MATERIAL PROTECCION',
    ];

    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $em): Response
    {
        $familyRepo  = $em->getRepository(Family::class);
        $productRepo = $em->getRepository(Product::class);

        // Familias visibles, sin duplicados, máx 8
        $allFamilies = $familyRepo->createQueryBuilder('f')
            ->andWhere('f.isActive = true')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();

        $blocked = array_map('mb_strtoupper', self::BLOCKED_FAMILIES);
        $seen    = [];
        $families = [];

        foreach ($allFamilies as $f) {
            $name = mb_strtoupper(trim((string) $f->getName()));
            if ($name === '' || in_array($name, $blocked, true) || in_array($name, $seen, true)) {
                continue;
            }
            $seen[]   = $name;
            $families[] = $f;
            if (count($families) >= 8) break;
        }

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
            'families'        => $families,
            'featuredProducts'=> $featuredProducts,
            'latestProducts'  => $latestProducts,
            'stats'           => $stats,
        ]);
    }
}
