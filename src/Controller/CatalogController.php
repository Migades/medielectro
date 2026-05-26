<?php

namespace App\Controller;

use App\Entity\Family;
use App\Entity\Product;
use App\Entity\Subfamily;
use App\Repository\FamilyRepository;
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
        $page   = max(1, $request->query->getInt('page', 1));
        $limit  = 24;
        $offset = ($page - 1) * $limit;

        $q             = trim((string) $request->query->get('q', ''));
        $familyId      = $request->query->getInt('family', 0);
        $subfamilyId   = $request->query->getInt('subfamily', 0);
        $subfamilyCode = trim((string) $request->query->get('code', ''));
        $inStock       = $request->query->getInt('inStock', 0);
        $brand         = trim((string) $request->query->get('brand', ''));
        $sort          = trim((string) $request->query->get('sort', 'recent'));

        $productRepo = $em->getRepository(Product::class);

        // ── Rango de precios global (para los límites del slider) ──
        $priceStats = $productRepo->createQueryBuilder('p')
            ->select('MIN(p.price) AS priceMin, MAX(p.price) AS priceMax')
            ->andWhere('p.isActive = true')
            ->getQuery()
            ->getSingleResult();

        $globalPriceMin = (int) floor((float) ($priceStats['priceMin'] ?? 0));
        $globalPriceMax = (int) ceil((float)  ($priceStats['priceMax'] ?? 9999));

        // Redondear el máximo a un número "limpio" para el slider
        $sliderMax = $this->roundUpNice($globalPriceMax);

        // Filtro de precio aplicado
        $priceMin = $request->query->has('priceMin')
            ? max($globalPriceMin, (int) $request->query->get('priceMin'))
            : $globalPriceMin;

        $priceMax = $request->query->has('priceMax')
            ? min($sliderMax, (int) $request->query->get('priceMax'))
            : $sliderMax;

        // ── Query principal ──
        $qb = $productRepo->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')->addSelect('f')
            ->leftJoin('p.subfamily', 's')->addSelect('s')
            ->andWhere('p.isActive = true');

        // MariaDB con utf8mb4_unicode_ci: LIKE ya es case-insensitive, no hace falta LOWER()
        if ($q !== '') {
            $qb->andWhere('(
                p.article LIKE :q
                OR p.model LIKE :q
                OR p.brand LIKE :q
                OR p.description LIKE :q
            )')
                ->setParameter('q', '%' . $q . '%');
        }

        if ($familyId > 0) {
            $qb->andWhere('f.id = :fid')->setParameter('fid', $familyId);
        }

        if ($subfamilyId > 0) {
            $qb->andWhere('s.id = :sid')->setParameter('sid', $subfamilyId);
        }

        if ($subfamilyCode !== '') {
            $qb->andWhere('s.code = :code')->setParameter('code', $subfamilyCode);
        }

        // Regla: "Solo disponibles" = stock >= 5
        if ($inStock === 1) {
            $qb->andWhere('p.stock >= :minStock')->setParameter('minStock', 5);
        }

        if ($brand !== '') {
            $qb->andWhere('p.brand = :brand')->setParameter('brand', $brand);
        }

        // Filtro precio — solo aplicar si el usuario ha estrechado el rango real
        $priceFilterActive = $priceMin > $globalPriceMin || $priceMax < $sliderMax;
        if ($priceFilterActive) {
            $qb->andWhere('p.price >= :pMin')->setParameter('pMin', $priceMin);
            $qb->andWhere('p.price <= :pMax')->setParameter('pMax', $priceMax);
        }

        switch ($sort) {
            case 'price_asc':  $qb->orderBy('p.price', 'ASC');  break;
            case 'price_desc': $qb->orderBy('p.price', 'DESC'); break;
            case 'stock_desc': $qb->orderBy('p.stock', 'DESC'); break;
            case 'brand_asc':  $qb->orderBy('p.brand', 'ASC');  break;
            default:           $qb->orderBy('p.id',    'DESC'); break;
        }

        $countQb = clone $qb;
        $total   = (int) $countQb->select('COUNT(p.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $products = $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalPages = (int) max(1, ceil($total / $limit));

        // ── Familias (lógica de bloqueo centralizada en FamilyRepository) ──
        $families = $em->getRepository(Family::class)->findVisible();

        // ── Subfamilias ──
        $subQb = $em->getRepository(Subfamily::class)->createQueryBuilder('s')
            ->leftJoin('s.family', 'f')->addSelect('f')
            ->orderBy('s.name', 'ASC');

        if ($familyId > 0) {
            $subQb->andWhere('f.id = :fid')->setParameter('fid', $familyId);
        }

        $subfamilies = $subQb->getQuery()->getResult();

        // ── Marcas ──
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
            'products'    => $products,
            'families'    => $families,
            'subfamilies' => $subfamilies,
            'brands'      => $brands,
            'filters'     => [
                'q'             => $q,
                'family'        => $familyId,
                'subfamily'     => $subfamilyId,
                'subfamilyCode' => $subfamilyCode,
                'inStock'       => $inStock,
                'brand'         => $brand,
                'sort'          => $sort,
                'priceMin'      => $priceMin,
                'priceMax'      => $priceMax,
            ],
            'priceRange' => [
                'globalMin' => $globalPriceMin,
                'globalMax' => $sliderMax,
                'active'    => $priceFilterActive,
            ],
            'currentPage'   => $page,
            'totalPages'    => $totalPages,
            'totalProducts' => $total,
        ]);
    }

    /** Redondea hacia arriba a un número "limpio" para el slider */
    private function roundUpNice(int $value): int
    {
        if ($value <= 100)  return 100;
        if ($value <= 500)  return (int) ceil($value / 50)  * 50;
        if ($value <= 1000) return (int) ceil($value / 100) * 100;
        if ($value <= 5000) return (int) ceil($value / 500) * 500;
        return (int) ceil($value / 1000) * 1000;
    }
}
