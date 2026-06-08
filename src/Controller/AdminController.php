<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
    public function logout(): void {}

    // ---------------------------------------------------------------------------
    // Dashboard — listado de pedidos
    // ---------------------------------------------------------------------------

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $status  = $request->query->get('status', '');
        $search  = trim($request->query->get('q', ''));
        $dateFilter = $request->query->get('date', '');

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

        if ($dateFilter) {
            $now = new \DateTimeImmutable();
            switch ($dateFilter) {
                case 'today':
                    $from = $now->setTime(0, 0, 0);
                    $to   = $now->setTime(23, 59, 59);
                    break;
                case 'week':
                    $from = $now->modify('monday this week')->setTime(0, 0, 0);
                    $to   = $now->setTime(23, 59, 59);
                    break;
                case 'month':
                    $from = $now->modify('first day of this month')->setTime(0, 0, 0);
                    $to   = $now->setTime(23, 59, 59);
                    break;
                default:
                    $from = $to = null;
            }
            if ($from && $to) {
                $qb->andWhere('o.createdAt BETWEEN :from AND :to')
                    ->setParameter('from', $from)
                    ->setParameter('to', $to);
            }
        }

        $orders = $qb->getQuery()->getResult();

        $stats = [
            'total'      => $this->orderRepo->count([]),
            'pending'    => $this->orderRepo->count(['status' => Order::STATUS_PENDING]),
            'confirmed'  => $this->orderRepo->count(['status' => Order::STATUS_CONFIRMED]),
            'preparing'  => $this->orderRepo->count(['status' => Order::STATUS_PREPARING]),
            'ready'      => $this->orderRepo->count(['status' => Order::STATUS_READY]),
            'shipped'    => $this->orderRepo->count(['status' => Order::STATUS_SHIPPED]),
        ];

        return $this->render('admin/index.html.twig', [
            'orders'       => $orders,
            'stats'        => $stats,
            'filterStatus' => $status,
            'filterQ'      => $search,
            'filterDate'   => $dateFilter,
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
    // Cambiar estado del pedido
    // ---------------------------------------------------------------------------

    #[Route('/pedido/{id}/estado', name: 'order_status', methods: ['POST'])]
    public function orderStatus(Order $order, Request $request): Response
    {
        $validStatuses = [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PREPARING,
            Order::STATUS_READY,
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

    // ---------------------------------------------------------------------------
    // Exportar pedidos a CSV
    // ---------------------------------------------------------------------------

    #[Route('/exportar-csv', name: 'export_csv', methods: ['GET'])]
    public function exportCsv(Request $request): StreamedResponse
    {
        $status = $request->query->get('status', '');
        $dateFilter = $request->query->get('date', '');

        $qb = $this->orderRepo->createQueryBuilder('o')
            ->leftJoin('o.customer', 'c')
            ->addSelect('c')
            ->orderBy('o.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }

        if ($dateFilter) {
            $now = new \DateTimeImmutable();
            switch ($dateFilter) {
                case 'today':
                    $from = $now->setTime(0, 0, 0);
                    $to   = $now->setTime(23, 59, 59);
                    break;
                case 'week':
                    $from = $now->modify('monday this week')->setTime(0, 0, 0);
                    $to   = $now->setTime(23, 59, 59);
                    break;
                case 'month':
                    $from = $now->modify('first day of this month')->setTime(0, 0, 0);
                    $to   = $now->setTime(23, 59, 59);
                    break;
                default:
                    $from = $to = null;
            }
            if (!empty($from) && !empty($to)) {
                $qb->andWhere('o.createdAt BETWEEN :from AND :to')
                    ->setParameter('from', $from)
                    ->setParameter('to', $to);
            }
        }

        $orders = $qb->getQuery()->getResult();

        $response = new StreamedResponse(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($handle, [
                'Referencia', 'Fecha', 'Estado', 'Cliente', 'Email',
                'Teléfono', 'Dirección', 'Total', 'Productos'
            ], ';');

            foreach ($orders as $order) {
                $customer = $order->getCustomer();
                $items = [];
                foreach ($order->getItems() as $item) {
                    $items[] = $item->getProductArticle() . ' x' . $item->getQuantity();
                }

                fputcsv($handle, [
                    $order->getReference(),
                    $order->getCreatedAt()->format('d/m/Y H:i'),
                    $order->getStatus(),
                    $customer ? $customer->getName() : '',
                    $customer ? $customer->getEmail() : '',
                    $customer ? $customer->getPhone() : '',
                    $customer ? $customer->getAddress() : '',
                    number_format($order->getTotal(), 2, ',', '.') . ' €',
                    implode(' | ', $items),
                ], ';');
            }

            fclose($handle);
        });

        $filename = 'pedidos_' . date('Ymd_His') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    // ---------------------------------------------------------------------------
    // Imprimir pedidos del día
    // ---------------------------------------------------------------------------

    #[Route('/imprimir-pedidos', name: 'print_orders', methods: ['GET'])]
    public function printOrders(): Response
    {
        $from = new \DateTimeImmutable('today 00:00:00');
        $to   = new \DateTimeImmutable('today 23:59:59');

        $orders = $this->orderRepo->createQueryBuilder('o')
            ->leftJoin('o.customer', 'c')
            ->addSelect('c')
            ->where('o.createdAt BETWEEN :from AND :to')
            ->andWhere("o.status != 'cancelled'")
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/print_orders.html.twig', [
            'orders' => $orders,
            'date'   => new \DateTimeImmutable(),
        ]);
    }
    // ---------------------------------------------------------------------------
    // Eliminar pedido
    // ---------------------------------------------------------------------------

    #[Route('/pedido/{id}/eliminar', name: 'order_delete', methods: ['POST'])]
    public function orderDelete(Order $order): Response
    {
        $this->em->remove($order);
        $this->em->flush();
        $this->addFlash('admin_success', 'Pedido eliminado correctamente.');
        return $this->redirectToRoute('app_admin_index');
    }
}
