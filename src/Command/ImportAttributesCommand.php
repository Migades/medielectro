<?php

namespace App\Command;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:attributes',
    description: 'Importa atributos de filtro desde el Excel de atributos (medielectro_atributos.xlsx).',
)]
class ImportAttributesCommand extends Command
{
    // Columnas que NO son atributos — se usan para identificar/ignorar
    private const SKIP_COLS = ['CODART', 'TITULO', 'MARCA'];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Ruta al archivo Excel de atributos')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simula sin guardar en BD')
            ->addOption('flush-every', null, InputOption::VALUE_REQUIRED, 'Flush cada N productos', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io         = new SymfonyStyle($input, $output);
        $file       = $input->getArgument('file');
        $dryRun     = (bool) $input->getOption('dry-run');
        $flushEvery = max(1, (int) $input->getOption('flush-every'));

        if (!file_exists($file)) {
            $io->error("El archivo no existe: $file");
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->warning('MODO DRY-RUN activo. No se guardará nada en BD.');
        }

        $io->title('Importación de atributos');

        $productRepo = $this->em->getRepository(Product::class);
        $stats = ['updated' => 0, 'not_found' => 0, 'skipped' => 0];
        $n = 0;

        // Leer el Excel con ZipArchive + DOMDocument (sin dependencias externas)
        $sheets = $this->readXlsx($file);

        foreach ($sheets as $sheetName => $rows) {
            if (count($rows) < 2) {
                continue;
            }

            // Encontrar la fila de cabeceras (la que tenga CODART)
            $headerRow = null;
            $headerIdx = null;
            foreach ($rows as $i => $row) {
                if (in_array('CODART', $row, true)) {
                    $headerRow = $row;
                    $headerIdx = $i;
                    break;
                }
            }

            if (!$headerRow) {
                $io->text("  Hoja '$sheetName': sin cabeceras, omitida.");
                continue;
            }

            $io->section($sheetName);
            $dataRows = array_slice($rows, $headerIdx + 1);

            // Quitar fila de notas/ejemplo si la primera celda no parece un código
            $dataRows = array_filter($dataRows, function ($row) {
                $code = trim($row[0] ?? '');
                return $code !== '' && is_numeric($code);
            });

            foreach ($dataRows as $row) {
                $codart = trim($row[0] ?? '');
                if ($codart === '') {
                    $stats['skipped']++;
                    continue;
                }

                $product = $productRepo->findOneBy(['article' => $codart]);
                if (!$product) {
                    $io->text("  No encontrado: $codart");
                    $stats['not_found']++;
                    continue;
                }

                // Construir array de atributos
                $attrs = $product->getAttributes();

                foreach ($headerRow as $colIdx => $colName) {
                    $colName = trim((string) $colName);
                    if ($colName === '' || in_array($colName, self::SKIP_COLS, true)) {
                        continue;
                    }

                    $value = trim((string) ($row[$colIdx] ?? ''));
                    if ($value === '') {
                        continue;
                    }

                    // Normalizar clave: minúsculas, sin unidades del nombre
                    $key = strtolower(preg_replace('/_[A-Z]+$/', '', $colName));

                    // Convertir a tipo correcto
                    if (is_numeric($value)) {
                        $attrs[$key] = strpos($value, '.') !== false ? (float) $value : (int) $value;
                    } else {
                        $attrs[$key] = $value;
                    }
                }

                $product->setAttributes($attrs);

                if (!$dryRun) {
                    $this->em->persist($product);
                }

                $stats['updated']++;
                $n++;

                if (!$dryRun && $n % $flushEvery === 0) {
                    $this->em->flush();
                }
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            'Completado: %d actualizados, %d no encontrados, %d omitidos.',
            $stats['updated'], $stats['not_found'], $stats['skipped']
        ));

        return Command::SUCCESS;
    }

    private function readXlsx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException("No se pudo abrir el archivo: $path");
        }

        // Leer strings compartidos
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $dom = new \DOMDocument();
            $dom->loadXML($ssXml, LIBXML_NOERROR);
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('ss', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($xpath->query('//ss:si') as $si) {
                $sharedStrings[] = $si->textContent;
            }
        }

        // Leer lista de hojas
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $dom = new \DOMDocument();
        $dom->loadXML($workbookXml, LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('wb', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $sheetNames = [];
        foreach ($xpath->query('//wb:sheet') as $sheet) {
            $sheetNames[] = $sheet->getAttribute('name');
        }

        $sheets = [];
        foreach ($sheetNames as $idx => $name) {
            $sheetNum = $idx + 1;
            $sheetXml = $zip->getFromName("xl/worksheets/sheet{$sheetNum}.xml");
            if (!$sheetXml) {
                continue;
            }

            $dom2 = new \DOMDocument();
            $dom2->loadXML($sheetXml, LIBXML_NOERROR);
            $xpath2 = new \DOMXPath($dom2);
            $xpath2->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

            $rows = [];
            foreach ($xpath2->query('//s:row') as $rowNode) {
                $row = [];
                $maxCol = 0;
                foreach ($xpath2->query('s:c', $rowNode) as $cell) {
                    $ref = $cell->getAttribute('r');
                    preg_match('/([A-Z]+)(\d+)/', $ref, $m);
                    $colIdx = $this->colToNum($m[1]) - 1;
                    $maxCol = max($maxCol, $colIdx);

                    $t = $cell->getAttribute('t');
                    $vNode = $xpath2->query('s:v', $cell)->item(0);
                    $val = $vNode ? $vNode->textContent : '';

                    if ($t === 's') {
                        $val = $sharedStrings[(int) $val] ?? '';
                    }

                    $row[$colIdx] = $val;
                }

                // Fill gaps
                $filled = [];
                for ($i = 0; $i <= $maxCol; $i++) {
                    $filled[] = $row[$i] ?? '';
                }
                $rows[] = $filled;
            }

            $sheets[$name] = $rows;
        }

        $zip->close();
        return $sheets;
    }

    private function colToNum(string $col): int
    {
        $num = 0;
        foreach (str_split($col) as $char) {
            $num = $num * 26 + (ord($char) - ord('A') + 1);
        }
        return $num;
    }
}
