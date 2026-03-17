<?php

namespace App\Controller;

use App\Cart\CartService;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/carrito', name: 'app_cart_')]
class CartController extends AbstractController
{
    public function __construct(
        private readonly CartService            $cart,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('cart/index.html.twig', [
            'items' => $this->cart->getItems(),
            'total' => $this->cart->getTotal(),
        ]);
    }

    /**
     * POST normal → redirect con flash.
     * XHR → JSON {count, total} para actualizar el badge del header sin recargar.
     */
    #[Route('/anadir/{article}', name: 'add', methods: ['POST'])]
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
                'count' => $this->cart->count(),
                'total' => number_format($this->cart->getTotal(), 2, ',', '.'),
            ]);
        }

        $this->addFlash('success', sprintf('"%s" añadido al carrito.', $product->getTitle() ?? $product->getModel()));
        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('app_cart_index'));
    }

    #[Route('/actualizar/{article}', name: 'update', methods: ['POST'])]
    public function update(string $article, Request $request): Response
    {
        $quantity = max(0, (int) $request->request->get('quantity', 1));
        $this->cart->updateQuantity($article, $quantity);

        if ($request->isXmlHttpRequest()) {
            $items = $this->cart->getItems();
            return new JsonResponse([
                'count'    => $this->cart->count(),
                'total'    => number_format($this->cart->getTotal(), 2, ',', '.'),
                'subtotal' => number_format($items[$article]?->getSubtotal() ?? 0, 2, ',', '.'),
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
                'count' => $this->cart->count(),
                'total' => number_format($this->cart->getTotal(), 2, ',', '.'),
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

    /** Valida el formulario y guarda datos del cliente en sesión antes del pago */
    #[Route('/procesar', name: 'process', methods: ['POST'])]
    public function process(Request $request): Response
    {
        if ($this->cart->isEmpty()) {
            return $this->redirectToRoute('app_cart_index');
        }

        $name    = trim((string) $request->request->get('name', ''));
        $email   = trim((string) $request->request->get('email', ''));
        $phone   = trim((string) $request->request->get('phone', ''));
        $address = trim((string) $request->request->get('address', ''));

        if (!$name || !$email || !$phone || !$address) {
            $this->addFlash('error', 'Por favor completa todos los campos obligatorios.');
            return $this->redirectToRoute('app_cart_checkout');
        }

        $request->getSession()->set('checkout_customer', compact('name', 'email', 'phone', 'address'));

        return $this->redirectToRoute('app_cart_payment');
    }

    /** Crea PaymentIntent en Stripe y renderiza el formulario de pago */
    #[Route('/pago', name: 'payment', methods: ['GET'])]
    public function payment(Request $request): Response
    {
        if ($this->cart->isEmpty()) {
            return $this->redirectToRoute('app_cart_index');
        }

        $customer = $request->getSession()->get('checkout_customer');
        if (!$customer) {
            return $this->redirectToRoute('app_cart_checkout');
        }

        $stripeSecret = $_ENV['STRIPE_SECRET_KEY'] ?? null;

        // Sin Stripe configurado → saltar directamente a confirmación (modo demo)
        if (!$stripeSecret) {
            $this->cart->clear();
            $request->getSession()->remove('checkout_customer');
            return $this->redirectToRoute('app_cart_confirmation');
        }

        try {
            \Stripe\Stripe::setApiKey($stripeSecret);
            $intent = \Stripe\PaymentIntent::create([
                'amount'   => (int) round($this->cart->getTotal() * 100),
                'currency' => 'eur',
                'metadata' => [
                    'customer_name'  => $customer['name'],
                    'customer_email' => $customer['email'],
                ],
            ]);

            return $this->render('cart/payment.html.twig', [
                'clientSecret'    => $intent->client_secret,
                'stripePublicKey' => $_ENV['STRIPE_PUBLIC_KEY'] ?? '',
                'items'           => $this->cart->getItems(),
                'total'           => $this->cart->getTotal(),
                'customer'        => $customer,
            ]);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Error al conectar con la pasarela de pago. Inténtalo de nuevo.');
            return $this->redirectToRoute('app_cart_checkout');
        }
    }

    /**
     * Webhook Stripe — escucha payment_intent.succeeded.
     * TODO: crear Order en BD, enviar email, notificar A3ERP.
     */
    #[Route('/webhook/stripe', name: 'stripe_webhook', methods: ['POST'])]
    public function stripeWebhook(Request $request): Response
    {
        $secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? null;
        if (!$secret) {
            return new Response('Webhook secret not configured', 400);
        }

        try {
            \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY'] ?? '');
            $event = \Stripe\Webhook::constructEvent(
                $request->getContent(),
                $request->headers->get('Stripe-Signature'),
                $secret
            );
        } catch (\Throwable $e) {
            return new Response('Invalid signature', 400);
        }

        if ($event->type === 'payment_intent.succeeded') {
            // TODO: Order::createFromPaymentIntent($event->data->object)
        }

        return new Response('OK', 200);
    }

    #[Route('/confirmacion', name: 'confirmation', methods: ['GET'])]
    public function confirmation(): Response
    {
        return $this->render('cart/confirmation.html.twig');
    }
}
