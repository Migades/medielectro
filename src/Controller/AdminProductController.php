<?php

namespace App\Controller;

use App\Entity\Family;
use App\Entity\Product;
use App\Entity\Subfamily;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/core-access-mel/productos', name: 'app_admin_product_')]
class AdminProductController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $productRepo,
        private readonly SluggerInterface $slugger,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $q = trim($request->query->get('q', ''));
        $active = $request->query->get('active', '');

        $qb = $this->productRepo->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')
            ->leftJoin('p.subfamily', 's')
            ->addSelect('f', 's')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(50);

        if ($q) {
            $qb->andWhere('p.article LIKE :q OR p.model LIKE :q OR p.brand LIKE :q OR p.title LIKE :q')
               ->setParameter('q', '%' . $q . '%');
        }

        if ($active !== '') {
            $qb->andWhere('p.isActive = :active')
               ->setParameter('active', (bool) $active);
        }

        $products = $qb->getQuery()->getResult();

        return $this->render('admin/products/index.html.twig', [
            'products' => $products,
            'filterQ'  => $q,
            'filterActive' => $active,
        ]);
    }

    #[Route('/nuevo', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $families = $this->em->getRepository(Family::class)->findAll();

        if ($request->isMethod('POST')) {
            $product = new Product();
            $this->fillProduct($product, $request);
            $this->em->persist($product);
            $this->em->flush();
            $this->addFlash('admin_success', 'Producto creado correctamente.');
            return $this->redirectToRoute('app_admin_product_index');
        }

        return $this->render('admin/products/form.html.twig', [
            'product'  => null,
            'families' => $families,
        ]);
    }

    #[Route('/{id}/editar', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Product $product, Request $request): Response
    {
        $families = $this->em->getRepository(Family::class)->findAll();

        if ($request->isMethod('POST')) {
            $this->fillProduct($product, $request);
            $this->em->flush();
            $this->addFlash('admin_success', 'Producto actualizado correctamente.');
            return $this->redirectToRoute('app_admin_product_index');
        }

        return $this->render('admin/products/form.html.twig', [
            'product'  => $product,
            'families' => $families,
        ]);
    }

    #[Route('/{id}/eliminar', name: 'delete', methods: ['POST'])]
    public function delete(Product $product): Response
    {
        $this->em->remove($product);
        $this->em->flush();
        $this->addFlash('admin_success', 'Producto eliminado.');
        return $this->redirectToRoute('app_admin_product_index');
    }

    #[Route('/subfamilias/{id}', name: 'subfamilies', methods: ['GET'])]
    public function subfamilies(Family $family): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $data = [];
        foreach ($family->getSubfamilies() as $s) {
            $data[] = ['id' => $s->getId(), 'name' => $s->getName()];
        }
        return $this->json($data);
    }

    private function fillProduct(Product $product, Request $request): void
    {
        $article = trim($request->request->get('article', ''));
        $model   = trim($request->request->get('model', ''));
        $brand   = trim($request->request->get('brand', ''));
        $price   = (float) str_replace(',', '.', $request->request->get('price', '0'));
        $stock   = (int) $request->request->get('stock', 0);
        $desc    = trim($request->request->get('description', ''));
        $active  = $request->request->get('isActive') === '1';
        $familyId    = (int) $request->request->get('family', 0);
        $subfamilyId = (int) $request->request->get('subfamily', 0);

        $product->setArticle($article);
        $product->setModel($model);
        $product->setBrand($brand ?: null);
        $product->setPrice((string) $price);
        $product->setStock($stock);
        $product->setDescription($desc ?: null);
        $product->setIsActive($active);
        $product->setObsolete(false);
        $product->setSlug(strtolower($this->slugger->slug($model . '-' . $article)));

        if ($familyId) {
            $family = $this->em->find(Family::class, $familyId);
            if ($family) $product->setFamily($family);
        }

        if ($subfamilyId) {
            $subfamily = $this->em->find(Subfamily::class, $subfamilyId);
            if ($subfamily) $product->setSubfamily($subfamily);
        }
    }
}
