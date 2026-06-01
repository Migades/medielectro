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
        $limit  = 12;
        $offset = max(0, $request->query->getInt('offset', 0));

        $q             = trim((string) $request->query->get('q', ''));
        $familyId      = $request->query->getInt('family', 0);
        $subfamilyId   = $request->query->getInt('subfamily', 0);
        $subfamilyCode = trim((string) $request->query->get('code', ''));
        $familyCode    = trim((string) $request->query->get('familyCode', ''));
        $codesRaw      = trim((string) $request->query->get('codes', ''));
        $codesList     = $codesRaw !== '' ? array_filter(array_map('trim', explode(',', $codesRaw))) : [];
        $attrsRaw = $request->query->all('attrs');
        $attrs = is_array($attrsRaw) ? array_filter($attrsRaw, fn($v) => $v !== null && $v !== '') : [];
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

        if ($familyCode !== '') {
            $qb->andWhere('f.code = :fcode')->setParameter('fcode', $familyCode);
        }

        if (!empty($codesList)) {
            $qb->andWhere('s.code IN (:codesList)')->setParameter('codesList', $codesList);
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

        $loaded  = $offset + count($products);
        $hasMore = $loaded < $total;

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

        // ── Marcas (filtradas por la misma query de productos activos) ──
        $brandsQb = $productRepo->createQueryBuilder('p')
            ->select('DISTINCT p.brand AS brand')
            ->leftJoin('p.family', 'f')
            ->leftJoin('p.subfamily', 's')
            ->andWhere('p.isActive = true')
            ->andWhere('p.brand IS NOT NULL')
            ->andWhere('p.brand != :empty')
            ->setParameter('empty', '');

        if ($q !== '') {
            $brandsQb->andWhere('(p.article LIKE :q2 OR p.model LIKE :q2 OR p.brand LIKE :q2 OR p.description LIKE :q2)')
                ->setParameter('q2', '%' . $q . '%');
        }
        if ($familyId > 0) {
            $brandsQb->andWhere('f.id = :fid2')->setParameter('fid2', $familyId);
        }
        if ($subfamilyCode !== '') {
            $brandsQb->andWhere('s.code = :code2')->setParameter('code2', $subfamilyCode);
        }
        if ($familyCode !== '') {
            $brandsQb->andWhere('f.code = :fcode2')->setParameter('fcode2', $familyCode);
        }
        if (!empty($codesList)) {
            $brandsQb->andWhere('s.code IN (:codesList2)')->setParameter('codesList2', $codesList);
        }

        $brandsQb->orderBy('p.brand', 'ASC');
        $brands = array_map(
            static fn(array $row) => $row['brand'],
            $brandsQb->getQuery()->getScalarResult()
        );

        // Opciones de atributos para los filtros del sidebar (solo si hay subfamilia)
        $attributeOptions = [];
        if ($subfamilyCode !== '') {
            $attrQb = $productRepo->createQueryBuilder('p')
                ->select('p.attributes')
                ->leftJoin('p.subfamily', 's')
                ->andWhere('p.isActive = true')
                ->andWhere('s.code = :sc')
                ->setParameter('sc', $subfamilyCode)
                ->andWhere('p.attributes IS NOT NULL');

            $allAttrs = $attrQb->getQuery()->getScalarResult();
            $collected = [];
            foreach ($allAttrs as $row) {
                $decoded = is_array($row['attributes']) ? $row['attributes'] : json_decode((string)$row['attributes'], true);
                if (!is_array($decoded)) continue;
                foreach ($decoded as $k => $v) {
                    if ($v === null || $v === '') continue;
                    $collected[$k][$v] = true;
                }
            }
            foreach ($collected as $k => $vals) {
                $keys = array_keys($vals);
                sort($keys);
                $attributeOptions[$k] = $keys;
            }
        }

        // Respuesta AJAX (load more)
        if ($request->isXmlHttpRequest()) {
            $html = $this->renderView('catalog/_products.html.twig', [
                'products' => $products,
            ]);
            return $this->json([
                'html'    => $html,
                'hasMore' => $hasMore,
                'loaded'  => $loaded,
                'total'   => $total,
            ]);
        }

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
                'familyCode'    => $familyCode,
                'codes'         => $codesRaw,
                'attrs'         => $attrs,
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
            'loaded'  => $loaded,
            'hasMore' => $hasMore,
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
