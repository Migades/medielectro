<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/core-access-mel', name: 'app_admin_')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository        $orderRepo,
    ) {}

    // ---------------------------------------------------------------------------
    // Login / Logout
    // ---------------------------------------------------------------------------

    #[Route('/login', name: 'login')]
    public function login(AuthenticationUtils $authUtils): Response
    {
        return $this->render('admin/login.html.twig', [
            'error'         => $authUtils->getLastAuthenticationError(),
            'last_username' => $authUtils->getLastUsername(),
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): void
    {
        // Symfony intercepta esta ruta — no necesita cuerpo
    }

    // ---------------------------------------------------------------------------
    // Dashboard — listado de pedidos
    // ---------------------------------------------------------------------------

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status', '');
        $search = trim($request->query->get('q', ''));

        $qb = $this->orderRepo->createQueryBuilder('o')
            ->leftJoin('o.customer', 'c')
            ->addSelect('c')
            ->orderBy('o.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }

        if ($search) {
            $qb->andWhere('o.reference LIKE :q OR c.name LIKE :q OR c.email LIKE :q')
                ->setParameter('q', '%' . $search . '%');
        }

        $orders = $qb->getQuery()->getResult();

        // Stats para las cards del dashboard
        $stats = [
            'total'     => $this->orderRepo->count([]),
            'pending'   => $this->orderRepo->count(['status' => Order::STATUS_PENDING]),
            'confirmed' => $this->orderRepo->count(['status' => Order::STATUS_CONFIRMED]),
            'shipped'   => $this->orderRepo->count(['status' => Order::STATUS_SHIPPED]),
        ];

        return $this->render('admin/index.html.twig', [
            'orders'    => $orders,
            'stats'     => $stats,
            'filterStatus' => $status,
            'filterQ'      => $search,
        ]);
    }

    // ---------------------------------------------------------------------------
    // Detalle de un pedido
    // ---------------------------------------------------------------------------

    #[Route('/pedido/{id}', name: 'order_show', methods: ['GET'])]
    public function orderShow(Order $order): Response
    {
        return $this->render('admin/order_show.html.twig', [
            'order' => $order,
        ]);
    }

    // ---------------------------------------------------------------------------
    // Cambiar estado del pedido (AJAX o form POST)
    // ---------------------------------------------------------------------------

    #[Route('/pedido/{id}/estado', name: 'order_status', methods: ['POST'])]
    public function orderStatus(Order $order, Request $request): Response
    {
        $validStatuses = [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
        ];

        $newStatus = $request->request->get('status', '');

        if (!in_array($newStatus, $validStatuses, true)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Estado no válido'], 400);
            }
            $this->addFlash('admin_error', 'Estado no válido.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        $order->setStatus($newStatus);
        $order->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true, 'status' => $newStatus]);
        }

        $this->addFlash('admin_success', 'Estado actualizado correctamente.');
        return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
    }
}
