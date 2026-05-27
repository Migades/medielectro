<?php

namespace App\Command;

use App\Entity\Family;
use App\Entity\Product;
use App\Entity\Subfamily;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:import:ods',
    description: 'Importa productos desde los archivos ODS del proveedor (HEPECASA).',
)]
class ImportOdsCommand extends Command
{
    /** Mapeo manual NOMBREFAMILIA (ODS) → nombre de Family en BD */
    private const FAMILY_MAP = [
        'gama blanca'              => 'Frigorífico y Congeladores', // fallback genérico gama blanca
        'climatizacion'            => 'Aire Acondicionado',
        'climatización'            => 'Aire Acondicionado',
        'calefaccion'              => 'Calefacción Eléctrica',
        'calefacción'              => 'Calefacción Eléctrica',
        'tv y sonido'              => 'Imagen y Sonido',
        'pequeños electrodomesticos' => 'Preparación de Alimentos',
        'ventilacion'              => 'Ventiladores',
        'ventilación'              => 'Ventiladores',
    ];

    /** Caché en memoria: subfamilyCode → Subfamily */
    private array $subfamilyCache = [];

    /** Caché en memoria: familyNameNormalized → Family */
    private array $familyCache = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SluggerInterface $slugger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('dir', InputArgument::REQUIRED, 'Ruta al directorio raíz con los archivos ODS')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simula la importación sin guardar en BD')
            ->addOption('flush-every', null, InputOption::VALUE_REQUIRED, 'Flush cada N productos', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io         = new SymfonyStyle($input, $output);
        $dir        = rtrim((string) $input->getArgument('dir'), '/\\');
        $dryRun     = (bool) $input->getOption('dry-run');
        $flushEvery = max(1, (int) $input->getOption('flush-every'));

        if (!is_dir($dir)) {
            $io->error("El directorio no existe: $dir");
            return Command::FAILURE;
        }

        $files = $this->findOdsFiles($dir);
        $io->title('Importación ODS — ' . count($files) . ' archivos encontrados');
        if ($dryRun) {
            $io->warning('MODO DRY-RUN activo. No se guardará nada en BD.');
        }

        // Pre-cargar todas las subfamilias y familias en caché
        $this->preloadCaches();

        $productRepo = $this->em->getRepository(Product::class);
        $stats       = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $n           = 0;

        foreach ($files as $file) {
            $io->section(
                basename(dirname($file)) . '/' . basename($file)
            );

            try {
                $rows = $this->readOds($file);
            } catch (\Throwable $e) {
                $io->error("No se pudo leer $file: " . $e->getMessage());
                $stats['errors']++;
                continue;
            }

            $dataRows = $this->extractDataRows($rows);
            $io->text(count($dataRows) . ' filas de producto.');

            foreach ($dataRows as $row) {
                try {
                    $article = $this->clean($row[1] ?? '');
                    if ($article === '') {
                        $stats['skipped']++;
                        continue;
                    }

                    $subfamilyCode = strtoupper($this->clean($row[5] ?? ''));
                    $subfamilyName = $this->clean($row[6] ?? '');
                    $nombrefamilia = $this->clean($row[16] ?? '');  // "GAMA BLANCA", "CLIMATIZACIÓN"…
                    $subcat        = $this->clean($row[18] ?? '');  // "FRIGORIFICOS Y CONGELADORES"…
                    $marca         = $this->clean($row[9] ?? '');
                    $modelo        = $this->clean($row[3] ?? '');
                    $ean           = $this->clean($row[4] ?? '') ?: null;
                    $preccompra    = $this->decimal($row[11] ?? '');
                    $margen        = $this->decimal($row[12] ?? '');
                    $pvpConIva     = $this->decimal($row[15] ?? '');

                    $price = round($preccompra + $margen, 2);
                    $pvpr  = $pvpConIva > 0 ? number_format($pvpConIva, 2, '.', '') : null;

                    if ($price <= 0) {
                        $stats['skipped']++;
                        continue;
                    }

                    $title = trim(implode(' ', array_filter([$marca, $modelo]))) ?: $article;

                    // ── Subfamily ──────────────────────────────────────────
                    $subfamily = $this->resolveSubfamily(
                        $subfamilyCode, $subfamilyName, $nombrefamilia, $subcat, $dryRun, $io
                    );

                    // ── Family ─────────────────────────────────────────────
                    $family = $subfamily?->getFamily()
                        ?? $this->resolveFamily($subcat, $nombrefamilia);

                    if (!$family) {
                        $io->warning("Sin familia para artículo $article (subfamily=$subfamilyCode, subcat=$subcat, fam=$nombrefamilia). Omitido.");
                        $stats['skipped']++;
                        continue;
                    }

                    // Enlazar subfamily a family si falta
                    if ($subfamily && $subfamily->getFamily() === null) {
                        $subfamily->setFamily($family);
                    }

                    // ── Product ────────────────────────────────────────────
                    $product = $productRepo->findOneBy(['article' => $article]);
                    $isNew   = !$product;

                    if ($isNew) {
                        $product = new Product();
                        $product->setArticle($article);
                    }

                    $product->setModel($modelo ?: '');
                    $product->setEan($ean ?: null);
                    $product->setTitle($title);
                    $product->setBrand($marca ?: null);
                    $product->setPrice(number_format($price, 2, '.', ''));
                    $product->setPvpr($pvpr);
                    $product->setStock(10);
                    $product->setIsActive(true);
                    $product->setObsolete(false);
                    $product->setVatCode('21');
                    $product->setFamily($family);
                    $product->setSubfamily($subfamily);
                    $product->setSlug(
                        mb_strtolower((string) $this->slugger->slug($title . '-' . $article))
                    );

                    if (!$dryRun) {
                        $this->em->persist($product);
                    }

                    $isNew ? $stats['created']++ : $stats['updated']++;
                    $n++;

                    if (!$dryRun && $n % $flushEvery === 0) {
                        $this->em->flush();
                    }

                } catch (\Throwable $e) {
                    $io->error("Error en artículo {$row[1]}: " . $e->getMessage());
                    $stats['errors']++;
                }
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            'Importacion completada: %d creados, %d actualizados, %d omitidos, %d errores.',
            $stats['created'], $stats['updated'], $stats['skipped'], $stats['errors']
        ));

        return Command::SUCCESS;
    }

    // ── Cache helpers ─────────────────────────────────────────────────────

    private function preloadCaches(): void
    {
        /** @var Subfamily[] $subs */
        $subs = $this->em->getRepository(Subfamily::class)->findAll();
        foreach ($subs as $s) {
            $this->subfamilyCache[$s->getCode()] = $s;
        }

        /** @var Family[] $fams */
        $fams = $this->em->getRepository(Family::class)->findAll();
        foreach ($fams as $f) {
            $this->familyCache[$this->normalize($f->getName())] = $f;
        }
    }

    private function resolveSubfamily(
        string $code, string $name, string $nombrefamilia, string $subcat,
        bool $dryRun, SymfonyStyle $io
    ): ?Subfamily {
        if ($code === '') {
            return null;
        }

        if (isset($this->subfamilyCache[$code])) {
            return $this->subfamilyCache[$code];
        }

        $subfamily = new Subfamily();
        $subfamily->setCode($code);
        $subfamily->setName($name ?: $code);
        $subfamily->setSlug(mb_strtolower((string) $this->slugger->slug($name ?: $code)));
        $subfamily->setIsActive(true);

        // Intentar asignar familia ya al crearla
        $family = $this->resolveFamily($subcat, $nombrefamilia);
        if ($family) {
            $subfamily->setFamily($family);
        }

        if (!$dryRun) {
            $this->em->persist($subfamily);
            $this->em->flush();
        }

        $this->subfamilyCache[$code] = $subfamily;
        $io->text("  [+] Subfamily: $code → $name");

        return $subfamily;
    }

    private function resolveFamily(string $subcat, string $nombrefamilia): ?Family
    {
        // 1. Buscar por NOMBRE DE SUBCATEGORIAS (más específico, p.ej. "FRIGORIFICOS Y CONGELADORES")
        if ($subcat !== '') {
            $key = $this->normalize($subcat);
            if (isset($this->familyCache[$key])) {
                return $this->familyCache[$key];
            }
        }

        // 2. Buscar por NOMBREFAMILIA directamente (p.ej. "GAMA BLANCA")
        if ($nombrefamilia !== '') {
            $key = $this->normalize($nombrefamilia);
            if (isset($this->familyCache[$key])) {
                return $this->familyCache[$key];
            }

            // 3. Mapeo manual NOMBREFAMILIA → Family
            if (isset(self::FAMILY_MAP[$key])) {
                $targetName = self::FAMILY_MAP[$key];
                $targetKey  = $this->normalize($targetName);
                if (isset($this->familyCache[$targetKey])) {
                    return $this->familyCache[$targetKey];
                }
            }
        }

        return null;
    }

    // ── ODS reader ────────────────────────────────────────────────────────

    private function findOdsFiles(string $dir): array
    {
        $files = [];
        $it    = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($it as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'ods') {
                $files[] = $file->getRealPath();
            }
        }
        sort($files);
        return $files;
    }

    private function readOds(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException("No se pudo abrir el ODS como ZIP: $path");
        }
        $xml = $zip->getFromName('content.xml');
        $zip->close();

        if ($xml === false) {
            throw new \RuntimeException("content.xml no encontrado en: $path");
        }

        $dom = new \DOMDocument();
        $dom->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
        $xpath->registerNamespace('text',  'urn:oasis:names:tc:opendocument:xmlns:text:1.0');

        $rows = [];
        foreach ($xpath->query('//table:table-row') as $rowNode) {
            $cells = [];
            foreach ($xpath->query('table:table-cell', $rowNode) as $cell) {
                $repeat = (int) ($cell->getAttributeNS(
                    'urn:oasis:names:tc:opendocument:xmlns:table:1.0',
                    'number-columns-repeated'
                ) ?: 1);
                $val = '';
                foreach ($xpath->query('text:p', $cell) as $p) {
                    $val = $p->textContent;
                    break;
                }
                for ($i = 0; $i < $repeat; $i++) {
                    $cells[] = $val;
                }
            }
            while ($cells && $cells[array_key_last($cells)] === '') {
                array_pop($cells);
            }
            if ($cells) {
                $rows[] = $cells;
            }
        }
        return $rows;
    }

    private function extractDataRows(array $rows): array
    {
        $data = [];
        foreach ($rows as $r) {
            if (count($r) < 6) {
                continue;
            }
            if ($r[0] === 'CODART') {
                continue;
            }
            if ($r[0] === '' && isset($r[1]) && trim($r[1]) !== '' && $r[1] !== 'CODIGO PROVEEDOR') {
                $data[] = $r;
            }
        }
        return $data;
    }

    // ── Utilidades ────────────────────────────────────────────────────────

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        return strtr($s, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
        ]);
    }

    private function clean(string $v): string
    {
        return trim($v);
    }

    private function decimal(string $v): float
    {
        $v = trim($v);
        if (preg_match('/^[\d.]+,\d+$/', $v)) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } elseif (preg_match('/^\d+,\d+$/', $v)) {
            $v = str_replace(',', '.', $v);
        }
        return (float) $v;
    }
}
