<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /** Genera la siguiente referencia del tipo ME-2026-00001 */
    public function generateReference(): string
    {
        $year  = date('Y');
        $prefix = 'ME-' . $year . '-';

        $last = $this->createQueryBuilder('o')
            ->select('o.reference')
            ->where('o.reference LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('o.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleColumnResult();

        $next = 1;
        if (!empty($last)) {
            $parts = explode('-', $last[0]);
            $next  = (int) end($parts) + 1;
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /** Pedidos pendientes de enviar al ERP */
    public function findPendingErpSync(): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.erpStatus = :status')
            ->setParameter('status', Order::ERP_PENDING)
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
