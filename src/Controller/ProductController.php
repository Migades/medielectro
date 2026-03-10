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

        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }
}
