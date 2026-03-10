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
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $em): Response
    {
        $familyRepo = $em->getRepository(Family::class);
        $productRepo = $em->getRepository(Product::class);

        $families = $familyRepo->createQueryBuilder('f')
            ->orderBy('f.name', 'ASC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        $featuredProducts = $productRepo->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')->addSelect('f')
            ->leftJoin('p.subfamily', 's')->addSelect('s')
            ->andWhere('p.isActive = true')
            ->andWhere('p.stock > 0')
            ->orderBy('p.stock', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        $latestProducts = $productRepo->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')->addSelect('f')
            ->andWhere('p.isActive = true')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        $stats = [
            'products' => (int) $productRepo->createQueryBuilder('p')->select('COUNT(p.id)')->getQuery()->getSingleScalarResult(),
            'families' => (int) $familyRepo->createQueryBuilder('f')->select('COUNT(f.id)')->getQuery()->getSingleScalarResult(),
            'inStock' => (int) $productRepo->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->andWhere('p.stock > 0')
                ->getQuery()
                ->getSingleScalarResult(),
        ];

        return $this->render('home/index.html.twig', [
            'families' => $families,
            'featuredProducts' => $featuredProducts,
            'latestProducts' => $latestProducts,
            'stats' => $stats,
        ]);
    }
}
