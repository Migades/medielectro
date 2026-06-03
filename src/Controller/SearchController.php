<?php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    #[Route('/api/search', name: 'app_search_suggest', methods: ['GET'])]
    public function suggest(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $q = trim($request->query->get('q', ''));

        if (strlen($q) < 2) {
            return $this->json([]);
        }

        $qb = $em->createQueryBuilder();
        $qb->select('p.article, p.title, p.model, p.brand, p.price, p.image, p.stock')
            ->from(Product::class, 'p')
            ->where('p.isActive = true')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(p.title)', ':q'),
                    $qb->expr()->like('LOWER(p.model)', ':q'),
                    $qb->expr()->like('LOWER(p.brand)', ':q'),
                    $qb->expr()->like('LOWER(p.article)', ':q')
                )
            )
            ->setParameter('q', '%' . strtolower($q) . '%')
            ->setMaxResults(6)
            ->orderBy('p.stock', 'DESC');

        $results = $qb->getQuery()->getArrayResult();

        $data = array_map(function($p) {
            return [
                'article' => $p['article'],
                'name'    => $p['title'] ?: $p['model'],
                'brand'   => $p['brand'],
                'price'   => number_format($p['price'], 2, ',', '.') . ' €',
                'image'   => $p['image'] ?: null,
                'stock'   => $p['stock'],
                'url'     => '/producto/' . $p['article'],
            ];
        }, $results);

        return $this->json($data);
    }
}
