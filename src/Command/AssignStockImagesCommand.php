<?php

namespace App\Command;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:product:stock-images',
    description: 'Asigna imágenes de stock (loremflickr) a productos sin imagen, agrupadas por subfamilia.',
)]
class AssignStockImagesCommand extends Command
{
    /**
     * Mapa código subfamilia → URL directa de loremflickr.com (600×600, temática, estable por lock).
     * loremflickr sirve la imagen directamente sin redireccionamiento, no requiere API key.
     */
    private const SUBFAMILY_IMAGES = [
        'FRI' => 'https://loremflickr.com/600/600/refrigerator,fridge/all?lock=2',
        'FRS' => 'https://loremflickr.com/600/600/refrigerator,american/all?lock=47',
        'FRH' => 'https://loremflickr.com/600/600/refrigerator,mini/all?lock=11',
        'FRO' => 'https://loremflickr.com/600/600/refrigerator,freezer/all?lock=89',
        'FRP' => 'https://loremflickr.com/600/600/refrigerator,builtin/all?lock=60',
        'FRJ' => 'https://loremflickr.com/600/600/refrigerator,integrated/all?lock=53',
        'COP' => 'https://loremflickr.com/600/600/freezer,vertical/all?lock=2',
        'CON' => 'https://loremflickr.com/600/600/freezer,chest/all?lock=3',
        'COQ' => 'https://loremflickr.com/600/600/freezer,builtin/all?lock=58',
        'VIN' => 'https://loremflickr.com/600/600/wine,cellar/all?lock=51',
        'MQH' => 'https://loremflickr.com/600/600/ice,machine/all?lock=22',
        'LAV' => 'https://loremflickr.com/600/600/washing,machine/all?lock=7',
        'LAS' => 'https://loremflickr.com/600/600/washing,machine/all?lock=14',
        'LAW' => 'https://loremflickr.com/600/600/washer,dryer/all?lock=5',
        'LAI' => 'https://loremflickr.com/600/600/washing,machine/all?lock=21',
        'LAX' => 'https://loremflickr.com/600/600/washing,machine/all?lock=33',
        'SCD' => 'https://loremflickr.com/600/600/dryer,tumble/all?lock=9',
        'SCE' => 'https://loremflickr.com/600/600/dryer,integrated/all?lock=16',
        'ENC' => 'https://loremflickr.com/600/600/gas,hob,kitchen/all?lock=4',
        'ENI' => 'https://loremflickr.com/600/600/induction,hob/all?lock=8',
        'ENX' => 'https://loremflickr.com/600/600/induction,hob/all?lock=19',
        'ENV' => 'https://loremflickr.com/600/600/ceramic,hob/all?lock=12',
        'ENT' => 'https://loremflickr.com/600/600/electric,hob/all?lock=6',
        'INC' => 'https://loremflickr.com/600/600/induction,kitchen/all?lock=31',
        'HNO' => 'https://loremflickr.com/600/600/oven,builtin/all?lock=10',
        'HNP' => 'https://loremflickr.com/600/600/oven,kitchen/all?lock=24',
        'HNN' => 'https://loremflickr.com/600/600/microwave,oven/all?lock=15',
        'HES' => 'https://loremflickr.com/600/600/oven,countertop/all?lock=37',
        'HNI' => 'https://loremflickr.com/600/600/gas,stove/all?lock=18',
        'HNE' => 'https://loremflickr.com/600/600/electric,burner/all?lock=42',
        'CHP' => 'https://loremflickr.com/600/600/oven,hob/all?lock=55',
        'COC' => 'https://loremflickr.com/600/600/kitchen,cooker/all?lock=27',
        'PVP' => 'https://loremflickr.com/600/600/ceramic,hob/all?lock=44',
        'PID' => 'https://loremflickr.com/600/600/induction,portable/all?lock=66',
        'VTE' => 'https://loremflickr.com/600/600/ceramic,portable/all?lock=73',
        'CAM' => 'https://loremflickr.com/600/600/kitchen,hood/all?lock=3',
        'CAP' => 'https://loremflickr.com/600/600/kitchen,extractor/all?lock=17',
        'CAS' => 'https://loremflickr.com/600/600/kitchen,hood/all?lock=28',
        'CAO' => 'https://loremflickr.com/600/600/kitchen,hood/all?lock=39',
        'CAK' => 'https://loremflickr.com/600/600/kitchen,island/all?lock=52',
        'CTH' => 'https://loremflickr.com/600/600/kitchen,ceiling/all?lock=61',
        'CAY' => 'https://loremflickr.com/600/600/kitchen,filter/all?lock=74',
        'GRF' => 'https://loremflickr.com/600/600/kitchen,filter/all?lock=83',
        'EXT' => 'https://loremflickr.com/600/600/kitchen,fan/all?lock=91',
        'FIL' => 'https://loremflickr.com/600/600/kitchen,filter/all?lock=100',
        'HMD' => 'https://loremflickr.com/600/600/microwave,builtin/all?lock=13',
        'CFI' => 'https://loremflickr.com/600/600/coffee,machine/all?lock=29',
        'LVI' => 'https://loremflickr.com/600/600/dishwasher,integrated/all?lock=41',
        'ACO' => 'https://loremflickr.com/600/600/air,conditioner/all?lock=5',
        'ACN' => 'https://loremflickr.com/600/600/air,conditioner,portable/all?lock=20',
        'ACH' => 'https://loremflickr.com/600/600/air,conditioner/all?lock=35',
        'ACQ' => 'https://loremflickr.com/600/600/air,conditioner/all?lock=48',
        'ACS' => 'https://loremflickr.com/600/600/air,conditioner/all?lock=62',
        'AER' => 'https://loremflickr.com/600/600/heat,pump/all?lock=76',
        'CLI' => 'https://loremflickr.com/600/600/air,cooler/all?lock=88',
        'PUR' => 'https://loremflickr.com/600/600/air,purifier/all?lock=6',
        'HUM' => 'https://loremflickr.com/600/600/humidifier/all?lock=23',
        'DES' => 'https://loremflickr.com/600/600/dehumidifier/all?lock=45',
        'AAC' => 'https://loremflickr.com/600/600/remote,control/all?lock=57',
        'AAI' => 'https://loremflickr.com/600/600/air,conditioner/all?lock=69',
        'AHD' => 'https://loremflickr.com/600/600/humidifier/all?lock=82',
        'RAD' => 'https://loremflickr.com/600/600/radiator,electric/all?lock=7',
        'ESE' => 'https://loremflickr.com/600/600/heater,electric/all?lock=26',
        'CNV' => 'https://loremflickr.com/600/600/heater,convector/all?lock=43',
        'TEV' => 'https://loremflickr.com/600/600/heater,fan/all?lock=59',
        'EMI' => 'https://loremflickr.com/600/600/heater,thermal/all?lock=72',
        'BRA' => 'https://loremflickr.com/600/600/heater,electric/all?lock=85',
        'SCT' => 'https://loremflickr.com/600/600/towel,rail,bathroom/all?lock=97',
        'CLP' => 'https://loremflickr.com/600/600/heater,electric/all?lock=11',
        'CLM' => 'https://loremflickr.com/600/600/blanket,electric/all?lock=34',
        'MAN' => 'https://loremflickr.com/600/600/blanket,electric/all?lock=50',
        'TER' => 'https://loremflickr.com/600/600/boiler,water/all?lock=64',
        'CAL' => 'https://loremflickr.com/600/600/water,heater/all?lock=79',
        'CLD' => 'https://loremflickr.com/600/600/boiler,gas/all?lock=93',
        'ESP' => 'https://loremflickr.com/600/600/stove,pellet/all?lock=16',
        'ACG' => 'https://loremflickr.com/600/600/boiler,accessory/all?lock=38',
        'ACD' => 'https://loremflickr.com/600/600/boiler,accessory/all?lock=54',
        'ATE' => 'https://loremflickr.com/600/600/water,heater/all?lock=67',
        'ACF' => 'https://loremflickr.com/600/600/heating,radiator/all?lock=80',
        'RUE' => 'https://loremflickr.com/600/600/wheels,furniture/all?lock=94',
        'TVK' => 'https://loremflickr.com/600/600/television,4k/all?lock=8',
        'TVD' => 'https://loremflickr.com/600/600/television,led/all?lock=25',
        'TVE' => 'https://loremflickr.com/600/600/television,smart/all?lock=46',
        'SOP' => 'https://loremflickr.com/600/600/television,mount/all?lock=63',
        'ALT' => 'https://loremflickr.com/600/600/speaker,bluetooth/all?lock=78',
        'CAH' => 'https://loremflickr.com/600/600/soundbar,speaker/all?lock=92',
        'CAD' => 'https://loremflickr.com/600/600/hifi,stereo/all?lock=9',
        'RCD' => 'https://loremflickr.com/600/600/radio,cd/all?lock=30',
        'REL' => 'https://loremflickr.com/600/600/radio,clock/all?lock=49',
        'DSP' => 'https://loremflickr.com/600/600/alarm,clock/all?lock=65',
        'GIR' => 'https://loremflickr.com/600/600/turntable,vinyl/all?lock=81',
        'MIC' => 'https://loremflickr.com/600/600/microphone/all?lock=96',
        'VIP' => 'https://loremflickr.com/600/600/projector,cinema/all?lock=14',
        'CAF' => 'https://loremflickr.com/600/600/coffee,drip/all?lock=36',
        'CAG' => 'https://loremflickr.com/600/600/coffee,capsule/all?lock=53',
        'CAE' => 'https://loremflickr.com/600/600/espresso,machine/all?lock=70',
        'CFA' => 'https://loremflickr.com/600/600/coffee,automatic/all?lock=86',
        'TOS' => 'https://loremflickr.com/600/600/toaster,kitchen/all?lock=4',
        'FRE' => 'https://loremflickr.com/600/600/deep,fryer/all?lock=19',
        'FRA' => 'https://loremflickr.com/600/600/air,fryer/all?lock=40',
        'ROB' => 'https://loremflickr.com/600/600/food,processor/all?lock=58',
        'GOF' => 'https://loremflickr.com/600/600/waffle,maker/all?lock=75',
        'CRE' => 'https://loremflickr.com/600/600/crepe,maker/all?lock=90',
        'PNF' => 'https://loremflickr.com/600/600/bread,maker/all?lock=10',
        'GRI' => 'https://loremflickr.com/600/600/sandwich,maker/all?lock=32',
        'ARR' => 'https://loremflickr.com/600/600/rice,cooker/all?lock=51',
        'HEL' => 'https://loremflickr.com/600/600/ice,cream/all?lock=68',
        'BAT' => 'https://loremflickr.com/600/600/blender,hand/all?lock=84',
        'BAV' => 'https://loremflickr.com/600/600/blender,jug/all?lock=99',
        'BAM' => 'https://loremflickr.com/600/600/mixer,stand/all?lock=15',
        'LIC' => 'https://loremflickr.com/600/600/juicer/all?lock=37',
        'OLL' => 'https://loremflickr.com/600/600/pressure,cooker/all?lock=56',
        'CAC' => 'https://loremflickr.com/600/600/cooking,pot/all?lock=71',
        'CAZ' => 'https://loremflickr.com/600/600/saucepan/all?lock=87',
        'SAR' => 'https://loremflickr.com/600/600/frying,pan/all?lock=1',
        'ASP' => 'https://loremflickr.com/600/600/vacuum,cleaner/all?lock=18',
        'ASE' => 'https://loremflickr.com/600/600/vacuum,cordless/all?lock=42',
        'ASM' => 'https://loremflickr.com/600/600/vacuum,handheld/all?lock=61',
        'ASR' => 'https://loremflickr.com/600/600/robot,vacuum/all?lock=77',
        'LSV' => 'https://loremflickr.com/600/600/steam,mop/all?lock=95',
        'LIM' => 'https://loremflickr.com/600/600/pressure,washer/all?lock=12',
        'SEC' => 'https://loremflickr.com/600/600/hair,dryer/all?lock=33',
        'AFE' => 'https://loremflickr.com/600/600/shaver,electric/all?lock=55',
        'DEP' => 'https://loremflickr.com/600/600/epilator/all?lock=73',
        'PLP' => 'https://loremflickr.com/600/600/hair,straightener/all?lock=89',
        'RIZ' => 'https://loremflickr.com/600/600/hair,curler/all?lock=6',
        'RID' => 'https://loremflickr.com/600/600/hair,curling/all?lock=28',
        'CTP' => 'https://loremflickr.com/600/600/hair,clipper/all?lock=47',
        'MGR' => 'https://loremflickr.com/600/600/beard,trimmer/all?lock=66',
        'CEP' => 'https://loremflickr.com/600/600/toothbrush,electric/all?lock=83',
        'TEN' => 'https://loremflickr.com/600/600/blood,pressure,monitor/all?lock=98',
        'TMM' => 'https://loremflickr.com/600/600/thermometer,digital/all?lock=13',
        'ALM' => 'https://loremflickr.com/600/600/heating,pad/all?lock=35',
        'SYB' => 'https://loremflickr.com/600/600/fitness,equipment/all?lock=54',
        'MAC' => 'https://loremflickr.com/600/600/sewing,machine/all?lock=72',
        'VEN' => 'https://loremflickr.com/600/600/fan,electric/all?lock=88',
        'ESB' => 'https://loremflickr.com/600/600/heater,gas/all?lock=3',
        'ACM' => 'https://loremflickr.com/600/600/electronics,cable/all?lock=21',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Sobreescribir productos que ya tienen imagen')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simula sin guardar en BD')
            ->addOption('flush-every', null, InputOption::VALUE_REQUIRED, 'Flush cada N productos', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io         = new SymfonyStyle($input, $output);
        $force      = (bool) $input->getOption('force');
        $dryRun     = (bool) $input->getOption('dry-run');
        $flushEvery = max(1, (int) $input->getOption('flush-every'));

        $io->title('Asignación de imágenes de stock por subfamilia');
        if ($dryRun) {
            $io->warning('MODO DRY-RUN activo.');
        }

        $qb = $this->em->getRepository(Product::class)
            ->createQueryBuilder('p')
            ->leftJoin('p.subfamily', 's')
            ->addSelect('s')
            ->andWhere('p.isActive = true');

        if (!$force) {
            $qb->andWhere('p.image IS NULL OR p.image = :empty OR p.image LIKE :sig')
               ->setParameter('empty', '')
               ->setParameter('sig', '%source.unsplash.com%');
        }

        $products = $qb->getQuery()->getResult();

        $stats = ['assigned' => 0, 'skipped' => 0];
        $n = 0;

        foreach ($products as $product) {
            $code = $product->getSubfamily()?->getCode();

            if (!$code || !isset(self::SUBFAMILY_IMAGES[$code])) {
                $stats['skipped']++;
                continue;
            }

            $product->setImage(self::SUBFAMILY_IMAGES[$code]);
            $stats['assigned']++;
            $n++;

            if (!$dryRun && $n % $flushEvery === 0) {
                $this->em->flush();
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            'Completado: %d imágenes asignadas, %d productos omitidos.',
            $stats['assigned'],
            $stats['skipped']
        ));

        return Command::SUCCESS;
    }
}
