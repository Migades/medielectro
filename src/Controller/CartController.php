<?php

namespace App\Controller;

use App\Cart\CartService;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Mail\OrderMailer;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/carrito', name: 'app_cart_')]
class CartController extends AbstractController
{
    public function __construct(
        private readonly CartService            $cart,
        private readonly EntityManagerInterface $em,
        private readonly OrderMailer            $mailer,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('cart/index.html.twig', [
            'items' => $this->cart->getItems(),
            'total' => $this->cart->getTotal(),
            'count' => $this->cart->getCount(),
        ]);
    }

    #[Route('/añadir/{article}', name: 'add', methods: ['POST'])]
    public function add(string $article, Request $request): Response
    {
        $product = $this->em->getRepository(Product::class)->findOneBy(['article' => $article]);

        if (!$product) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Producto no encontrado'], 404);
            }
            throw $this->createNotFoundException();
        }

        $quantity = max(1, (int) $request->request->get('quantity', 1));
        $this->cart->add($product, $quantity);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'count'   => $this->cart->getCount(),
                'total'   => $this->cart->getTotal(),
            ]);
        }

        $this->addFlash('cart_success', sprintf('"%s" añadido al carrito.', $product->getTitle() ?? $product->getModel()));
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/actualizar/{article}', name: 'update', methods: ['POST'])]
    public function update(string $article, Request $request): Response
    {
        $this->cart->updateQuantity($article, (int) $request->request->get('quantity', 1));

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'count'   => $this->cart->getCount(),
                'total'   => $this->cart->getTotal(),
            ]);
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/eliminar/{article}', name: 'remove', methods: ['POST'])]
    public function remove(string $article, Request $request): Response
    {
        $this->cart->remove($article);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'count'   => $this->cart->getCount(),
                'total'   => $this->cart->getTotal(),
            ]);
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/checkout', name: 'checkout', methods: ['GET'])]
    public function checkout(): Response
    {
        if ($this->cart->isEmpty()) {
            return $this->redirectToRoute('app_cart_index');
        }

        return $this->render('cart/checkout.html.twig', [
            'items' => $this->cart->getItems(),
            'total' => $this->cart->getTotal(),
        ]);
    }

    #[Route('/confirmar', name: 'confirm', methods: ['POST'])]
    public function confirm(Request $request): Response
    {
        if ($this->cart->isEmpty()) {
            return $this->redirectToRoute('app_cart_index');
        }

        $name    = trim((string) $request->request->get('name', ''));
        $email   = trim((string) $request->request->get('email', ''));
        $phone   = trim((string) $request->request->get('phone', ''));
        $address = trim((string) $request->request->get('address', ''));
        $notes   = trim((string) $request->request->get('notes', ''));

        if (!$name || !$email || !$phone) {
            $this->addFlash('checkout_error', 'Por favor, completa todos los campos obligatorios.');
            return $this->redirectToRoute('app_cart_checkout');
        }

        $company = trim((string) $request->request->get('company', ''));
        $zip     = trim((string) $request->request->get('zip', ''));
        $city    = trim((string) $request->request->get('city', ''));

        // Crear o recuperar cliente por email
        $customerRepo = $this->em->getRepository(\App\Entity\Customer::class);
        $customer = $customerRepo->findOrCreate($email, $name, $phone);
        $customer->setCompany($company ?: null);
        $customer->setAddress($address ?: null);
        $customer->setZip($zip ?: null);
        $customer->setCity($city ?: null);
        $this->em->persist($customer);

        // Crear pedido
        $order = new \App\Entity\Order();
        $orderRepo = $this->em->getRepository(\App\Entity\Order::class);
        $order->setReference($orderRepo->generateReference());
        $order->setCustomer($customer);
        $order->setShippingAddress($address ?: null);
        $order->setShippingZip($zip ?: null);
        $order->setShippingCity($city ?: null);
        $order->setNotes($notes ?: null);
        $order->setTotal((string) $this->cart->getTotal());
        $this->em->persist($order);

        // Crear líneas
        foreach ($this->cart->getItems() as $item) {
            $line = new \App\Entity\OrderLine();
            $line->setProductArticle($item->article);
            $line->setProductTitle($item->title);
            $line->setProductBrand($item->brand ?: null);
            $line->setUnitPrice((string) $item->price);
            $line->setQuantity($item->quantity);
            $line->calcSubtotal();
            $order->addLine($line);
            $this->em->persist($line);
        }

        $this->em->flush();

        // Enviar emails — en dev se capturan en el Symfony Profiler
        try {
            $this->mailer->sendOrderConfirmation($order);
            $this->mailer->sendInternalNotification($order);
        } catch (\Throwable $e) {
            // No bloquear el flujo si falla el email — loguear en producción
        }

        // Guardar referencia en sesión para mostrar en confirmación
        $request->getSession()->set('last_order', [
            'reference' => $order->getReference(),
            'name'      => $name,
            'email'     => $email,
            'phone'     => $phone,
            'address'   => $address,
            'notes'     => $notes,
            'items'     => $this->cart->getItems(),
            'total'     => $this->cart->getTotal(),
        ]);

        $this->cart->clear();

        // TODO siguiente fase: enviar email de confirmación, sincronizar con A3ERP
        return $this->redirectToRoute('app_cart_confirmed');
    }

    #[Route('/confirmado', name: 'confirmed', methods: ['GET'])]
    public function confirmed(Request $request): Response
    {
        $order = $request->getSession()->get('last_order');

        if (!$order) {
            return $this->redirectToRoute('app_home');
        }

        $request->getSession()->remove('last_order');

        return $this->render('cart/confirmed.html.twig', ['order' => $order]);
    }
}
