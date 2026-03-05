<?php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CatalogController extends AbstractController
{
    #[Route('/catalogo', name: 'app_catalog')]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $productRepository = $entityManager->getRepository(Product::class);

        $queryBuilder = $productRepository
            ->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')
            ->addSelect('f')
            ->leftJoin('p.subfamily', 's')
            ->addSelect('s')
            ->orderBy('p.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $products = $queryBuilder->getQuery()->getResult();

        $totalProducts = (int) $productRepository
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = (int) ceil($totalProducts / $limit);

        return $this->render('catalog/index.html.twig', [
            'products' => $products,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalProducts' => $totalProducts,
            'limit' => $limit,
        ]);
    }
}
