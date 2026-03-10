<?php

namespace App\Command;

use App\Entity\CsvImport;
use App\Entity\Family;
use App\Entity\Product;
use App\Entity\Subfamily;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:import:csv',
    description: 'Importa catálogo desde CSV y registra la importación.',
)]
class ImportCsvCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SluggerInterface $slugger,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'Ruta del archivo CSV a importar. Ejemplo: var/import/inbox/20260305_600140.csv'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $file = (string) $input->getArgument('file');

        if (!$this->filesystem->exists($file)) {
            $io->error(sprintf('No existe el archivo: %s', $file));
            return Command::FAILURE;
        }

        $import = new CsvImport();
        $import->setFilename(basename($file));
        $import->setStatus('running');
        $import->setTotalRows(0);
        $import->setImportedRows(0);
        $import->setErrorRows(0);
        $import->setStartedAt(new \DateTimeImmutable());

        $this->em->persist($import);
        $this->em->flush();

        $totalRows = 0;
        $importedRows = 0;
        $errorRows = 0;

        $batchSize = 200;

        try {
            $handle = fopen($file, 'r');

            if ($handle === false) {
                throw new \RuntimeException(sprintf('No se pudo abrir el archivo: %s', $file));
            }

            // Leer cabecera
            $headers = fgetcsv($handle, 0, ';', '"', '\\');

            if ($headers === false) {
                throw new \RuntimeException('El CSV está vacío o no tiene cabecera.');
            }

            // Normalizar cabeceras (BOM + espacios)
            $headers = array_map(
                static fn ($header) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)),
                $headers
            );

            $indexes = $this->buildHeaderIndexMap($headers, [
                'ARTICULO',
                'MODELO',
                'EAN',
                'SUBFAMILIA',
                'NOMBRE_SUBFAMILIA',
                'DESCRIPCION',
                'MARCA',
                'PRECIO',
                'STOCK',
                'PVPR',
                'IVA_TECNO',
                'OBSOLETO',
                'CANONDIGITAL',
                'FAMILIA',
                'NOMBREFAMILIA',
            ]);

            $familyRepository = $this->em->getRepository(Family::class);
            $subfamilyRepository = $this->em->getRepository(Subfamily::class);
            $productRepository = $this->em->getRepository(Product::class);

            while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
                $totalRows++;

                try {
                    $article = $this->getValue($row, $indexes['ARTICULO']);
                    $model = $this->getValue($row, $indexes['MODELO']);
                    $ean = $this->nullIfEmpty($this->getValue($row, $indexes['EAN']));

                    $familyCode = $this->getValue($row, $indexes['FAMILIA']);
                    $familyName = $this->getValue($row, $indexes['NOMBREFAMILIA']);

                    $subfamilyCode = $this->getValue($row, $indexes['SUBFAMILIA']);
                    $subfamilyName = $this->nullIfEmpty($this->getValue($row, $indexes['NOMBRE_SUBFAMILIA'])) ?? 'Sin subfamilia';

                    $description = $this->nullIfEmpty($this->getValue($row, $indexes['DESCRIPCION']));
                    $brand = $this->nullIfEmpty($this->getValue($row, $indexes['MARCA']));

                    $price = $this->parseDecimal($this->getValue($row, $indexes['PRECIO']));
                    $stock = (int) $this->getValue($row, $indexes['STOCK']);

                    $pvprRaw = $this->nullIfEmpty($this->getValue($row, $indexes['PVPR']));
                    $pvpr = $pvprRaw !== null ? $this->parseDecimal($pvprRaw) : null;

                    $ivaTecno = $this->nullIfEmpty($this->getValue($row, $indexes['IVA_TECNO']));
                    $obsoleteRaw = $this->nullIfEmpty($this->getValue($row, $indexes['OBSOLETO']));
                    $obsolete = $this->isTruthyMark($obsoleteRaw);

                    $digitalCanonRaw = $this->nullIfEmpty($this->getValue($row, $indexes['CANONDIGITAL']));
                    $digitalCanon = $digitalCanonRaw !== null ? $this->parseDecimal($digitalCanonRaw) : null;

                    // FAMILY
                    /** @var Family|null $family */
                    $family = $familyRepository->findOneBy(['code' => $familyCode]);

                    if (!$family) {
                        $family = new Family();
                        $family->setCode($familyCode);
                        $this->em->persist($family);
                    }

                    $family->setName($familyName);
                    $family->setSlug($this->slug($familyName));
                    $family->setIsActive(true);

                    // SUBFAMILY
                    /** @var Subfamily|null $subfamily */
                    $subfamily = $subfamilyRepository->findOneBy([
                        'code' => $subfamilyCode,
                        'family' => $family,
                    ]);

                    if (!$subfamily) {
                        $subfamily = new Subfamily();
                        $subfamily->setCode($subfamilyCode);
                        $subfamily->setFamily($family);
                        $this->em->persist($subfamily);
                    }

                    $subfamily->setName($subfamilyName);
                    $subfamily->setSlug($this->slug($subfamilyName));
                    $subfamily->setIsActive(true);

                    // PRODUCT
                    /** @var Product|null $product */
                    $product = $productRepository->findOneBy(['article' => $article]);

                    if (!$product) {
                        $product = new Product();
                        $product->setArticle($article);
                        $this->em->persist($product);
                    }

                    $product->setModel($model);
                    $product->setEan($ean);
                    $product->setDescription($description);

                    // ✅ TITLE (cara al usuario): description -> (brand + family + model)
                    $product->setTitle($this->buildTitleFromValues($description, $brand, $model, $familyName));

                    $product->setBrand($brand);
                    $product->setPrice($price);
                    $product->setStock($stock);
                    $product->setPvpr($pvpr);
                    $product->setIvaTecno($ivaTecno);
                    $product->setObsolete($obsolete);
                    $product->setDigitalCanon($digitalCanon);
                    $product->setFamily($family);
                    $product->setSubfamily($subfamily);

                    $slugBase = $description ?: $model ?: $article;
                    $product->setSlug($this->slug($slugBase . '-' . $article));
                    $product->setIsActive(!$obsolete);

                    $importedRows++;

                    if (($totalRows % $batchSize) === 0) {
                        $this->em->flush();
                        $this->em->clear();

                        $familyRepository = $this->em->getRepository(Family::class);
                        $subfamilyRepository = $this->em->getRepository(Subfamily::class);
                        $productRepository = $this->em->getRepository(Product::class);
                    }
                } catch (\Throwable $exception) {
                    $errorRows++;
                }
            }

            fclose($handle);

            $this->em->flush();

            $import->setTotalRows($totalRows);
            $import->setImportedRows($importedRows);
            $import->setErrorRows($errorRows);
            $import->setStatus('success');
            $import->setFinishedAt(new \DateTimeImmutable());
            $import->setMessage(sprintf('Importación completada. OK: %d | ERR: %d', $importedRows, $errorRows));

            $this->em->flush();

            $processedDir = 'var/import/processed';
            $this->filesystem->mkdir($processedDir);

            $targetPath = sprintf(
                '%s/%s_%s.csv',
                $processedDir,
                pathinfo($file, PATHINFO_FILENAME),
                date('Ymd_His')
            );

            $this->filesystem->rename($file, $targetPath, true);

            $io->success(sprintf('Import OK. Total: %d | OK: %d | ERR: %d', $totalRows, $importedRows, $errorRows));
            $io->writeln(sprintf('Movido a: %s', $targetPath));

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $import->setTotalRows($totalRows);
            $import->setImportedRows($importedRows);
            $import->setErrorRows($errorRows);
            $import->setStatus('error');
            $import->setFinishedAt(new \DateTimeImmutable());
            $import->setMessage($exception->getMessage());

            $this->em->flush();

            $errorDir = 'var/import/error';
            $this->filesystem->mkdir($errorDir);

            $targetPath = sprintf(
                '%s/%s_%s.csv',
                $errorDir,
                pathinfo($file, PATHINFO_FILENAME),
                date('Ymd_His')
            );

            try {
                $this->filesystem->rename($file, $targetPath, true);
            } catch (\Throwable) {
                // No hacemos nada aquí para no tapar el error principal.
            }

            $io->error('Import ERROR: ' . $exception->getMessage());

            return Command::FAILURE;
        }
    }

    private function slug(string $value): string
    {
        return strtolower($this->slugger->slug($value)->toString());
    }

    private function nullIfEmpty(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function getValue(array $row, int $index): string
    {
        return isset($row[$index]) ? trim((string) $row[$index]) : '';
    }

    private function isTruthyMark(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $value = strtoupper(trim($value));

        return in_array($value, ['X', '1', 'SI', 'S', 'TRUE'], true);
    }

    private function parseDecimal(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '0';
        }

        // Ejemplos:
        // "20,52" -> "20.52"
        // "1.234,56" -> "1234.56"
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);

        if (!is_numeric($value)) {
            return '0';
        }

        return $value;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, string> $requiredHeaders
     * @return array<string, int>
     */
    private function buildHeaderIndexMap(array $headers, array $requiredHeaders): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $map[$header] = $index;
        }

        $result = [];

        foreach ($requiredHeaders as $requiredHeader) {
            if (!array_key_exists($requiredHeader, $map)) {
                throw new \RuntimeException(sprintf('Falta la columna requerida: %s', $requiredHeader));
            }

            $result[$requiredHeader] = $map[$requiredHeader];
        }

        return $result;
    }

    // ========= NUEVO: helpers para title "cara usuario" =========

    private function cleanText(?string $v): string
    {
        $v = trim((string) $v);
        $v = preg_replace('/\s+/', ' ', $v);
        return $v;
    }

    private function buildTitleFromValues(?string $description, ?string $brand, ?string $model, ?string $familyName): string
    {
        $desc = $this->cleanText($description);
        if ($desc !== '') {
            return $desc;
        }

        $b = $this->cleanText($brand);
        $m = $this->cleanText($model);
        $f = $this->cleanText($familyName);

        // Plan B si no hay descripción: algo legible
        $title = trim(($b !== '' ? $b : 'Producto') . ' ' . ($f !== '' ? $f : '') . ' ' . $m);

        return trim($title) !== '' ? trim($title) : 'Producto';
    }
}
