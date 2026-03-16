<?php

namespace App\Controller;

use App\Entity\Family;
use App\Entity\Product;
use App\Entity\Subfamily;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CatalogController extends AbstractController
{
    #[Route('/catalogo', name: 'app_catalog')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        $q = trim((string) $request->query->get('q', ''));
        $familyId = $request->query->getInt('family', 0);
        $subfamilyId = $request->query->getInt('subfamily', 0);
        $inStock = $request->query->getInt('inStock', 0);
        $brand = trim((string) $request->query->get('brand', ''));
        $sort = trim((string) $request->query->get('sort', 'recent'));

        $productRepo = $em->getRepository(Product::class);

        $qb = $productRepo->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')->addSelect('f')
            ->leftJoin('p.subfamily', 's')->addSelect('s')
            ->andWhere('p.isActive = true');

        if ($q !== '') {
            $qb->andWhere('(
                LOWER(p.article) LIKE :q
                OR LOWER(p.model) LIKE :q
                OR LOWER(p.brand) LIKE :q
                OR LOWER(p.description) LIKE :q
            )')
                ->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        if ($familyId > 0) {
            $qb->andWhere('f.id = :fid')->setParameter('fid', $familyId);
        }

        if ($subfamilyId > 0) {
            $qb->andWhere('s.id = :sid')->setParameter('sid', $subfamilyId);
        }

        // ✅ Regla jefe: "Solo disponibles" = stock >= 5
        if ($inStock === 1) {
            $qb->andWhere('p.stock >= :minStock')
                ->setParameter('minStock', 5);
        }

        if ($brand !== '') {
            $qb->andWhere('p.brand = :brand')->setParameter('brand', $brand);
        }

        switch ($sort) {
            case 'price_asc':
                $qb->orderBy('p.price', 'ASC');
                break;
            case 'price_desc':
                $qb->orderBy('p.price', 'DESC');
                break;
            case 'stock_desc':
                $qb->orderBy('p.stock', 'DESC');
                break;
            case 'brand_asc':
                $qb->orderBy('p.brand', 'ASC');
                break;
            default:
                $qb->orderBy('p.id', 'DESC');
                break;
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(p.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $products = $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalPages = (int) max(1, ceil($total / $limit));

        $families = $em->getRepository(Family::class)->createQueryBuilder('f')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Filtrar familias no vendibles (internas)
        $blockedFamilies = [
            'CARGO PUBLICIDAD CLIENTES',
            'MERCHANDISING',
            'MATERIAL PROTECCION',
        ];

        $families = array_values(array_filter($families, static function ($f) use ($blockedFamilies) {
            $name = mb_strtoupper(trim((string) $f->getName()));
            return $name !== '' && !in_array($name, $blockedFamilies, true);
        }));

        $subQb = $em->getRepository(Subfamily::class)->createQueryBuilder('s')
            ->leftJoin('s.family', 'f')->addSelect('f')
            ->orderBy('s.name', 'ASC');

        if ($familyId > 0) {
            $subQb->andWhere('f.id = :fid')->setParameter('fid', $familyId);
        }

        $subfamilies = $subQb->getQuery()->getResult();

        $brands = $productRepo->createQueryBuilder('p')
            ->select('DISTINCT p.brand AS brand')
            ->andWhere('p.brand IS NOT NULL')
            ->andWhere('p.brand != :empty')
            ->setParameter('empty', '')
            ->orderBy('p.brand', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $brands = array_map(static fn(array $row) => $row['brand'], $brands);

        return $this->render('catalog/index.html.twig', [
            'products' => $products,
            'families' => $families,
            'subfamilies' => $subfamilies,
            'brands' => $brands,
            'filters' => [
                'q' => $q,
                'family' => $familyId,
                'subfamily' => $subfamilyId,
                'inStock' => $inStock,
                'brand' => $brand,
                'sort' => $sort,
            ],
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalProducts' => $total,
        ]);
    }
}
