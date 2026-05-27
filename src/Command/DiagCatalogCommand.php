<?php

namespace App\Command;

use App\Entity\Product;
use App\Entity\Subfamily;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:diag:catalog',
    description: 'Diagnóstico: muestra códigos de subfamilia y cuántos productos tiene cada una.',
)]
class DiagCatalogCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // ── 1. Subfamilias registradas en BD ──────────────────────────────
        $subfamilies = $this->em->getRepository(Subfamily::class)
            ->createQueryBuilder('s')
            ->orderBy('s.code', 'ASC')
            ->getQuery()
            ->getResult();

        $io->title('Subfamilias en BD (' . count($subfamilies) . ')');

        $rows = [];
        foreach ($subfamilies as $s) {
            $rows[] = [
                $s->getCode(),
                $s->getName(),
                $s->getFamily()?->getName() ?? '(sin familia)',
                $s->isActive() ? 'si' : 'no',
            ];
        }
        $io->table(['Codigo', 'Nombre', 'Familia', 'Activa'], $rows);

        // ── 2. Productos por código de subfamilia ─────────────────────────
        $io->title('Productos activos por subfamilia');

        $counts = $this->em->getRepository(Product::class)
            ->createQueryBuilder('p')
            ->select('s.code AS code, s.name AS name, COUNT(p.id) AS total')
            ->leftJoin('p.subfamily', 's')
            ->andWhere('p.isActive = true')
            ->groupBy('s.code, s.name')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getScalarResult();

        $rows2 = [];
        foreach ($counts as $row) {
            $rows2[] = [
                $row['code'] ?? '(NULL)',
                $row['name'] ?? '(sin subfamilia)',
                $row['total'],
            ];
        }
        $io->table(['Codigo subfamilia', 'Nombre', 'Productos activos'], $rows2);

        // ── 3. Productos sin subfamilia ───────────────────────────────────
        $sinSubfamilia = (int) $this->em->getRepository(Product::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.isActive = true')
            ->andWhere('p.subfamily IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        if ($sinSubfamilia > 0) {
            $io->warning("$sinSubfamilia productos activos NO tienen subfamilia asignada.");
        } else {
            $io->success('Todos los productos activos tienen subfamilia.');
        }

        return Command::SUCCESS;
    }
}
