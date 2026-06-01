<?php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SeoController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'app_sitemap', defaults: ['_format' => 'xml'])]
    public function sitemap(EntityManagerInterface $em): Response
    {
        $products = $em->getRepository(Product::class)
            ->findBy(['isActive' => true], ['id' => 'DESC']);

        $response = $this->render('seo/sitemap.xml.twig', [
            'products' => $products,
        ]);

        $response->headers->set('Content-Type', 'application/xml');
        return $response;
    }

    #[Route('/robots.txt', name: 'app_robots')]
    public function robots(): Response
    {
        $content = "User-agent: *\n";
        $content .= "Disallow: /admin\n";
        $content .= "Disallow: /cart\n";
        $content .= "Disallow: /checkout\n";
        $content .= "\n";
        $content .= "Sitemap: https://medielectro.es/sitemap.xml\n";

        return new Response($content, 200, ['Content-Type' => 'text/plain']);
    }
}
