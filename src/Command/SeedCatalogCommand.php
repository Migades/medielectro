<?php

namespace App\Command;

use App\Entity\Family;
use App\Entity\Subfamily;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:catalog:seed',
    description: 'Inserta en BD las familias y subfamilias del catálogo definidas en el JSON.',
)]
class SeedCatalogCommand extends Command
{
    private const CATALOG = [
        'Electrodomésticos' => [
            'Frigorífico y Congeladores' => [
                ['codigo' => 'FRI', 'nombre' => 'FRIGORIFICO 2 PUERTAS'],
                ['codigo' => 'FRS', 'nombre' => 'FRIGORIFICO AMERICANO'],
                ['codigo' => 'FRH', 'nombre' => 'FRIGORIFICO 1 PUERTA'],
                ['codigo' => 'FRO', 'nombre' => 'FRIGORIFICO COMBI'],
                ['codigo' => 'FRP', 'nombre' => 'COMBINADO INTEGRABLE'],
                ['codigo' => 'FRJ', 'nombre' => 'FRIGORIFICO INTEGRABLE'],
                ['codigo' => 'COP', 'nombre' => 'CONGELADOR VERTICAL'],
                ['codigo' => 'CON', 'nombre' => 'CONGELADOR HORIZONTAL'],
                ['codigo' => 'COQ', 'nombre' => 'CONGELADOR INTEGRABLE'],
                ['codigo' => 'VIN', 'nombre' => 'VINOTECA'],
                ['codigo' => 'MQH', 'nombre' => 'MAQUINA HACER HIELO'],
            ],
            'Lavado y Secado' => [
                ['codigo' => 'LAV', 'nombre' => 'LAVADORA CARGA FRONTAL'],
                ['codigo' => 'LAS', 'nombre' => 'LAVADORA CARGA SUPERIOR'],
                ['codigo' => 'LAW', 'nombre' => 'LAVADORA-SECADORA'],
                ['codigo' => 'LAI', 'nombre' => 'LAVADORA SECADORA INTEGRABLE'],
                ['codigo' => 'LAX', 'nombre' => 'LAVADORA INTEGRABLE'],
                ['codigo' => 'SCD', 'nombre' => 'SECADORA CARGA FRONTAL'],
                ['codigo' => 'SCE', 'nombre' => 'SECADORA INTEGRABLE'],
            ],
            'Cocina' => [
                ['codigo' => 'ENC', 'nombre' => 'ENCIMERA A GAS'],
                ['codigo' => 'ENI', 'nombre' => 'ENCIMERA POR INDUCCION'],
                ['codigo' => 'ENX', 'nombre' => 'ENCIMERA INDUCCION MIXTA'],
                ['codigo' => 'ENV', 'nombre' => 'ENCIMERA VITROCERAMICA'],
                ['codigo' => 'ENT', 'nombre' => 'ENCIMERA ELECTRICA'],
                ['codigo' => 'INC', 'nombre' => 'ENCIMERA INDUCCION Y EXTRACTOR'],
                ['codigo' => 'HNO', 'nombre' => 'HORNO ENCASTRE'],
                ['codigo' => 'HNP', 'nombre' => 'HORNO POLIVALENTE'],
                ['codigo' => 'HNN', 'nombre' => 'HORNO COMPACTO'],
                ['codigo' => 'HES', 'nombre' => 'HORNO ELECTRICO SOBREMESA'],
                ['codigo' => 'HNI', 'nombre' => 'HORNILLO A GAS'],
                ['codigo' => 'HNE', 'nombre' => 'HORNILLO ELECTRICO'],
                ['codigo' => 'CHP', 'nombre' => 'CONJUNTO DE HORNO Y PLACA'],
                ['codigo' => 'COC', 'nombre' => 'COCINA'],
                ['codigo' => 'PVP', 'nombre' => 'PLACA VITRO PORTATIL'],
                ['codigo' => 'PID', 'nombre' => 'PLACA INDUCCION PORTATIL'],
                ['codigo' => 'VTE', 'nombre' => 'PLACA VITROCERAMICA PORTATIL'],
            ],
            'Campanas y Extractores' => [
                ['codigo' => 'CAM', 'nombre' => 'CAMPANA DECORATIVA'],
                ['codigo' => 'CAP', 'nombre' => 'CAMPANA EXTRAIBLE TELESCOPICA'],
                ['codigo' => 'CAS', 'nombre' => 'CAMPANA CLASICA CONVENCIONAL'],
                ['codigo' => 'CAO', 'nombre' => 'CAMPANA INTEGRABLES'],
                ['codigo' => 'CAK', 'nombre' => 'CAMPANA ISLA'],
                ['codigo' => 'CTH', 'nombre' => 'CAMPANA DE TECHO'],
                ['codigo' => 'CAY', 'nombre' => 'GRUPO FILTRANTES'],
                ['codigo' => 'GRF', 'nombre' => 'GRUPO FILTRANTES DE HUMO'],
                ['codigo' => 'EXT', 'nombre' => 'EXTRACTOR'],
                ['codigo' => 'FIL', 'nombre' => 'FILTRO CAMPANA'],
            ],
            'Integrables' => [
                ['codigo' => 'HMD', 'nombre' => 'HORNO MICROONDAS INTEGRACION'],
                ['codigo' => 'CFI', 'nombre' => 'CAFETERA INTEGRACION'],
                ['codigo' => 'LVI', 'nombre' => 'LAVAVAJILLAS INTEGRABLES'],
            ],
        ],
        'Climatización' => [
            'Aire Acondicionado' => [
                ['codigo' => 'ACO', 'nombre' => 'ACONDICIONADORES DE SPLIT'],
                ['codigo' => 'ACN', 'nombre' => 'ACONDICIONADORES PORTATILES'],
                ['codigo' => 'ACH', 'nombre' => 'ACONDICIONADORES DE TECHO'],
                ['codigo' => 'ACQ', 'nombre' => 'ACONDICIONADORES DE CONDUCTO'],
                ['codigo' => 'ACS', 'nombre' => 'ACONDICIONADORES DE SUELO'],
                ['codigo' => 'AER', 'nombre' => 'AEROTERMIA'],
                ['codigo' => 'CLI', 'nombre' => 'CLIMATIZADOR'],
            ],
            'Calidad del Aire' => [
                ['codigo' => 'PUR', 'nombre' => 'PURIFICADOR'],
                ['codigo' => 'HUM', 'nombre' => 'HUMIDIFICADOR'],
                ['codigo' => 'DES', 'nombre' => 'DESHUMIDIFICADOR'],
            ],
            'Accesorios Climatización' => [
                ['codigo' => 'AAC', 'nombre' => 'ACCESORIO AIRE ACONDICIONADO'],
                ['codigo' => 'AAI', 'nombre' => 'ACCESORIO ACOND AIRE'],
                ['codigo' => 'AHD', 'nombre' => 'ACCESORIO HUMIDIFICADOR'],
            ],
        ],
        'Calefacción' => [
            'Calefacción Eléctrica' => [
                ['codigo' => 'RAD', 'nombre' => 'RADIADOR ELECTRICO'],
                ['codigo' => 'ESE', 'nombre' => 'ESTUFA ELECTRICA'],
                ['codigo' => 'CNV', 'nombre' => 'CONVECTOR'],
                ['codigo' => 'TEV', 'nombre' => 'TERMOVENTILADOR'],
                ['codigo' => 'EMI', 'nombre' => 'EMISOR TERMICO'],
                ['codigo' => 'BRA', 'nombre' => 'BRASERO ELECTRICO'],
                ['codigo' => 'SCT', 'nombre' => 'TOALLERO ELECTRICO'],
                ['codigo' => 'CLP', 'nombre' => 'CALIENTAPIE'],
                ['codigo' => 'CLM', 'nombre' => 'CALIENTACAMA'],
                ['codigo' => 'MAN', 'nombre' => 'MANTA ELECTRICA'],
                ['codigo' => 'TER', 'nombre' => 'TERMO ELECTRICO'],
            ],
            'Calentadores y Calderas' => [
                ['codigo' => 'CAL', 'nombre' => 'CALENTADOR DE AGUA'],
                ['codigo' => 'CLD', 'nombre' => 'CALDERA'],
            ],
            'Sistemas Avanzados' => [
                ['codigo' => 'ESP', 'nombre' => 'ESTUFA DE PELLETS'],
            ],
            'Accesorios Calefacción' => [
                ['codigo' => 'ACG', 'nombre' => 'ACCESORIO CALENTADOR'],
                ['codigo' => 'ACD', 'nombre' => 'ACCESORIO PARA CALDERA'],
                ['codigo' => 'ATE', 'nombre' => 'ACCESORIO TERMO'],
                ['codigo' => 'ACF', 'nombre' => 'ACCESORIO PARA CALEFACCION'],
                ['codigo' => 'RUE', 'nombre' => 'RUEDAS PATAS DE CALEFACCION'],
            ],
        ],
        'TV y Sonido' => [
            'Televisores' => [
                ['codigo' => 'TVK', 'nombre' => 'TELEVISOR 4K'],
                ['codigo' => 'TVD', 'nombre' => 'TELEVISOR LED'],
                ['codigo' => 'TVE', 'nombre' => 'TELEVISOR SMART TV'],
                ['codigo' => 'SOP', 'nombre' => 'SOPORTES'],
            ],
            'Imagen y Sonido' => [
                ['codigo' => 'ALT', 'nombre' => 'ALTAVOZ'],
                ['codigo' => 'CAH', 'nombre' => 'BARRA DE SONIDO'],
                ['codigo' => 'CAD', 'nombre' => 'CADENA DE SONIDO'],
                ['codigo' => 'RCD', 'nombre' => 'RADIO CD'],
                ['codigo' => 'REL', 'nombre' => 'RADIO-RELOJ'],
                ['codigo' => 'DSP', 'nombre' => 'DESPERTADOR'],
                ['codigo' => 'GIR', 'nombre' => 'GIRADISCOS'],
                ['codigo' => 'MIC', 'nombre' => 'MICROFONO'],
                ['codigo' => 'VIP', 'nombre' => 'PROYECTOR'],
            ],
        ],
        'Pequeños Electrodomésticos' => [
            'Cafeteras' => [
                ['codigo' => 'CAF', 'nombre' => 'CAFETERA DE GOTEO'],
                ['codigo' => 'CAG', 'nombre' => 'CAFETERA CAPSULAS'],
                ['codigo' => 'CAE', 'nombre' => 'CAFETERA EXPRESS'],
                ['codigo' => 'CFA', 'nombre' => 'CAFETERA AUTOMATICA Y SEMI'],
            ],
            'Preparación de Alimentos' => [
                ['codigo' => 'TOS', 'nombre' => 'TOSTADORA'],
                ['codigo' => 'FRE', 'nombre' => 'FREIDORA'],
                ['codigo' => 'FRA', 'nombre' => 'FREIDORA DE AIRE'],
                ['codigo' => 'ROB', 'nombre' => 'ROBOT DE COCINA'],
                ['codigo' => 'GOF', 'nombre' => 'GOFRERA'],
                ['codigo' => 'CRE', 'nombre' => 'CREPERA'],
                ['codigo' => 'PNF', 'nombre' => 'PANIFICADORA'],
                ['codigo' => 'GRI', 'nombre' => 'SANDWICHERA'],
                ['codigo' => 'ARR', 'nombre' => 'ARROCERA'],
                ['codigo' => 'HEL', 'nombre' => 'HELADERA'],
            ],
            'Batidoras' => [
                ['codigo' => 'BAT', 'nombre' => 'BATIDORA'],
                ['codigo' => 'BAV', 'nombre' => 'BATIDORA DE VASO'],
                ['codigo' => 'BAM', 'nombre' => 'BATIDORA AMASADORA'],
                ['codigo' => 'LIC', 'nombre' => 'LICUADORA'],
            ],
            'Ollas y Sartenes' => [
                ['codigo' => 'OLL', 'nombre' => 'OLLA A PRESION'],
                ['codigo' => 'CAC', 'nombre' => 'CACEROLA'],
                ['codigo' => 'CAZ', 'nombre' => 'CAZO'],
                ['codigo' => 'SAR', 'nombre' => 'SARTEN'],
            ],
            'Limpieza del Hogar' => [
                ['codigo' => 'ASP', 'nombre' => 'ASPIRADOR TRINEO'],
                ['codigo' => 'ASE', 'nombre' => 'ASPIRADOR ESCOBA'],
                ['codigo' => 'ASM', 'nombre' => 'ASPIRADOR MANO'],
                ['codigo' => 'ASR', 'nombre' => 'ASPIRADOR ROBOT'],
                ['codigo' => 'LSV', 'nombre' => 'LIMPIADORA VAPOR'],
                ['codigo' => 'LIM', 'nombre' => 'LIMPIEZA A PRESION'],
            ],
            'Cuidado Personal' => [
                ['codigo' => 'SEC', 'nombre' => 'SECADOR'],
                ['codigo' => 'AFE', 'nombre' => 'AFEITADORA'],
                ['codigo' => 'DEP', 'nombre' => 'DEPILADORA'],
                ['codigo' => 'PLP', 'nombre' => 'PLANCHA PELO'],
                ['codigo' => 'RIZ', 'nombre' => 'RIZAPELO'],
                ['codigo' => 'RID', 'nombre' => 'RIZADOR'],
                ['codigo' => 'CTP', 'nombre' => 'CORTAPELO'],
                ['codigo' => 'MGR', 'nombre' => 'MAQUINA DE CORTAR PELO'],
                ['codigo' => 'CEP', 'nombre' => 'CEPILLO DENTAL'],
            ],
            'Salud y Bienestar' => [
                ['codigo' => 'TEN', 'nombre' => 'TENSIOMETRO'],
                ['codigo' => 'TMM', 'nombre' => 'TERMOMETRO'],
                ['codigo' => 'ALM', 'nombre' => 'ALMOHADILLA ELECTRICA'],
                ['codigo' => 'SYB', 'nombre' => 'MAQUINARIA DEPORTIVA'],
            ],
            'Máquinas de Coser' => [
                ['codigo' => 'MAC', 'nombre' => 'MAQUINA DE COSER'],
            ],
        ],
        'Ventilación' => [
            'Ventiladores' => [
                ['codigo' => 'VEN', 'nombre' => 'VENTILADOR'],
            ],
            'Calefacción Gas' => [
                ['codigo' => 'ESB', 'nombre' => 'ESTUFA BUTANO'],
            ],
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SluggerInterface       $slugger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Muestra qué se insertaría sin escribir en BD'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $familyRepo    = $this->em->getRepository(Family::class);
        $subfamilyRepo = $this->em->getRepository(Subfamily::class);

        $familiesCreated     = 0;
        $familiesExisting    = 0;
        $subfamiliesCreated  = 0;
        $subfamiliesUpdated  = 0;
        $subfamiliesExisting = 0;

        $io->title('Seed del catálogo — Familias y Subfamilias');

        if ($dryRun) {
            $io->warning('Modo dry-run: no se escribirá nada en la BD.');
        }

        foreach (self::CATALOG as $menuLabel => $groups) {
            $io->section($menuLabel);

            foreach ($groups as $familyName => $items) {

                $familyCode = mb_strtoupper(
                    preg_replace('/[^A-Z0-9]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT', $familyName)) ?? $familyName
                );
                $familySlug        = mb_strtolower((string) $this->slugger->slug($familyName));
                $familyNameUpper   = mb_strtoupper($familyName);

                // Busca por nombre en mayúsculas O por código para evitar duplicados
                $family = $familyRepo->createQueryBuilder('f')
                    ->where('UPPER(f.name) = :name OR f.code = :code')
                    ->setParameter('name', $familyNameUpper)
                    ->setParameter('code', $familyCode)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if (!$family) {
                    $family = new Family();
                    $family->setCode($familyCode);
                    $family->setName($familyNameUpper);
                    $family->setSlug($familySlug);
                    $family->setIsActive(true);

                    if (!$dryRun) {
                        $this->em->persist($family);
                        $this->em->flush();
                    }

                    $io->text(sprintf('  [+] Family creada: <info>%s</info> (código: %s)', $familyName, $familyCode));
                    $familiesCreated++;
                } else {
                    $io->text(sprintf('  [=] Family existente: <comment>%s</comment>', $familyName));
                    $familiesExisting++;
                }

                foreach ($items as $item) {
                    $code = $item['codigo'];
                    $name = $item['nombre'];
                    $slug = mb_strtolower((string) $this->slugger->slug($name));

                    $subfamily = $subfamilyRepo->findOneBy(['code' => $code]);

                    if (!$subfamily) {
                        $subfamily = new Subfamily();
                        $subfamily->setCode($code);
                        $subfamily->setName($name);
                        $subfamily->setSlug($slug);
                        $subfamily->setIsActive(true);
                        $subfamily->setFamily($family);

                        if (!$dryRun) {
                            $this->em->persist($subfamily);
                        }

                        $io->text(sprintf('      [+] Subfamily creada: <info>%s</info> → %s', $code, $name));
                        $subfamiliesCreated++;
                    } else {
                        if ($subfamily->getFamily()?->getName() !== $family->getName()) {
                            $subfamily->setFamily($family);
                            $io->text(sprintf('      [~] Subfamily actualizada familia: <comment>%s</comment> → %s', $code, $name));
                            $subfamiliesUpdated++;
                        } else {
                            $subfamiliesExisting++;
                        }
                    }
                }

                if (!$dryRun) {
                    $this->em->flush();
                }
            }
        }

        $io->success(sprintf(
            "Familias: %d creadas, %d ya existían.\nSubfamilias: %d creadas, %d actualizadas, %d ya existían.",
            $familiesCreated,
            $familiesExisting,
            $subfamiliesCreated,
            $subfamiliesUpdated,
            $subfamiliesExisting,
        ));

        return Command::SUCCESS;
    }
}
