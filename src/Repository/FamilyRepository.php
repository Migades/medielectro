<?php

namespace App\Repository;

use App\Entity\Family;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Family>
 */
class FamilyRepository extends ServiceEntityRepository
{
    /** Familias internas que nunca deben mostrarse al cliente */
    public const BLOCKED = [
        'CARGO PUBLICIDAD CLIENTES',
        'MERCHANDISING',
        'MATERIAL PROTECCION',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Family::class);
    }

    /**
     * Familias visibles al cliente: activas, sin duplicados de nombre, sin bloqueadas.
     * Ordenadas alfabéticamente.
     *
     * @return Family[]
     */
    public function findVisible(): array
    {
        /** @var Family[] $all */
        $all = $this->createQueryBuilder('f')
            ->andWhere('f.isActive = true')
            ->orderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();

        $blocked = array_map('mb_strtoupper', self::BLOCKED);
        $seen    = [];
        $result  = [];

        foreach ($all as $family) {
            $name = mb_strtoupper(trim((string) $family->getName()));
            if ($name === '' || in_array($name, $blocked, true) || in_array($name, $seen, true)) {
                continue;
            }
            $seen[]   = $name;
            $result[] = $family;
        }

        return $result;
    }

    /**
     * Familias visibles con sus subfamilias precargadas (para el mega-menú).
     *
     * @return Family[]
     */
    public function findVisibleWithSubfamilies(): array
    {
        /** @var Family[] $all */
        $all = $this->createQueryBuilder('f')
            ->leftJoin('f.subfamilies', 's')
            ->addSelect('s')
            ->andWhere('f.isActive = true')
            ->orderBy('f.id', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        $blocked = array_map('mb_strtoupper', self::BLOCKED);
        $seen    = [];
        $result  = [];

        foreach ($all as $family) {
            $name = mb_strtoupper(trim((string) $family->getName()));
            if ($name === '' || in_array($name, $blocked, true) || in_array($name, $seen, true)) {
                continue;
            }
            $seen[]   = $name;
            $result[] = $family;
        }

        return $result;
    }
}
