<?php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    #[Route('/producto/{article}', name: 'app_product_show')]
    public function show(string $article, EntityManagerInterface $em): Response
    {
        $product = $em->getRepository(Product::class)->findOneBy(['article' => $article]);

        if (!$product) {
            throw $this->createNotFoundException('Producto no encontrado');
        }

        // Related products: same subfamily, exclude current, limit 4
        $qb = $em->createQueryBuilder();
        $qb->select('p')
            ->from(Product::class, 'p')
            ->where('p.article != :article')
            ->andWhere('p.isActive = true')
            ->setParameter('article', $article)
            ->setMaxResults(4);

        if ($product->getSubfamily()) {
            $qb->andWhere('p.subfamily = :subfamily')
               ->setParameter('subfamily', $product->getSubfamily());
        } elseif ($product->getFamily()) {
            $qb->andWhere('p.family = :family')
               ->setParameter('family', $product->getFamily());
        }

        $related = $qb->getQuery()->getResult();

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'related' => $related,
        ]);
    }
}
