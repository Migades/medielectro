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
    name: 'app:product:fetch-images',
    description: 'Busca imágenes de productos por Marca+Modelo usando Open Icecat.',
)]
class FetchProductImagesCommand extends Command
{
    private const PRIORITY_EANS = [
        '4242003599433','8414706005573','8414706005559','8017709297718','8436555987473',
        '7332543173754','6901018064777','6901018069611','6901018072697','8003437236365',
        '8007842783810','9005382253496','9005382167090','4016803187189','4242006292409',
        '8421152160305','8421152160299','6901018062544','6901018069222','6901018058974',
        '8050147002292','8059019005881','8422248102131','8059019005904','8059019014807',
        '8003437041389','4242003884492','4016803055532','8003437046520','8434778021882',
        '8690769371057','4242003877654','4242003877647','4242003864258','4242003877661',
        '8422248100687','8422248100748','6921727065940','6921727076700','6921727076694',
        '4242005422081','8435025785786','4242005126552','7332543717873','7333394026633',
        '9005382100837','9005382235355','9005382279717','9005382167359','9005382234037',
        '8436607759362','8414234210012','8414234210029','8435568406520','8435484093255',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $icecatUser,
        private readonly string $icecatPass,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit',    'l', InputOption::VALUE_OPTIONAL, 'Máximo de productos (default: 200)', 200)
            ->addOption('dry-run',  null, InputOption::VALUE_NONE,    'Sin escribir en BD')
            ->addOption('force',    'f',  InputOption::VALUE_NONE,    'Incluir productos que ya tienen imagen')
            ->addOption('priority', 'p',  InputOption::VALUE_NONE,    'Solo EANs del ODS FRIGORIFICOS_Y_CONGELADORES');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $limit    = (int) $input->getOption('limit');
        $dryRun   = (bool) $input->getOption('dry-run');
        $force    = (bool) $input->getOption('force');
        $priority = (bool) $input->getOption('priority');

        $io->title('Fetch Product Images — Open Icecat (Brand + ProductCode)');
        if ($dryRun) $io->warning('Modo dry-run: no se escribirá en BD.');

        $repo = $this->em->getRepository(Product::class);

        if ($priority) {
            $io->text('<comment>Modo prioridad: EANs de FRIGORIFICOS_Y_CONGELADORES</comment>');
            $qb = $repo->createQueryBuilder('p')
                ->andWhere('p.isActive = true')
                ->andWhere('p.ean IN (:eans)')
                ->setParameter('eans', self::PRIORITY_EANS);
        } else {
            $qb = $repo->createQueryBuilder('p')
                ->andWhere('p.isActive = true')
                ->andWhere('p.brand IS NOT NULL')
                ->andWhere('p.brand != :empty')
                ->setParameter('empty', '');
        }

        if (!$force) $qb->andWhere('p.image IS NULL');

        /** @var Product[] $products */
        $products = $qb->setMaxResults($limit)->getQuery()->getResult();

        $io->text(sprintf('Productos a procesar: <info>%d</info>', count($products)));
        if (empty($products)) { $io->success('Sin productos pendientes.'); return Command::SUCCESS; }

        $found = $notFound = $batch = 0;

        foreach ($products as $product) {
            $brand = trim((string) $product->getBrand());
            $model = trim((string) $product->getModel());

            if ($brand === '' || $model === '') {
                $io->text(sprintf('  [SKIP] %s — sin marca o modelo', $product->getArticle()));
                continue;
            }

            if ($batch > 0) usleep(500000); // 0.5s entre peticiones

            $imageUrl = $this->fetchFromIcecat($brand, $model);
            $batch++;

            if ($imageUrl !== null) {
                $io->text(sprintf(
                    '  [OK]  %s (%s %s) → <info>%s</info>',
                    $product->getArticle(), $brand, $model,
                    substr($imageUrl, 0, 70) . '...'
                ));
                if (!$dryRun) $product->setImage($imageUrl);
                $found++;
            } else {
                $io->text(sprintf('  [--]  %s (%s %s) — sin ficha', $product->getArticle(), $brand, $model));
                $notFound++;
            }

            if (!$dryRun && $found > 0 && $found % 50 === 0) {
                $this->em->flush();
                $io->text(sprintf('  → <comment>Guardados %d en BD</comment>', $found));
            }
        }

        if (!$dryRun) $this->em->flush();

        $io->success(sprintf('Completado — Con imagen: %d | Sin ficha: %d', $found, $notFound));
        return Command::SUCCESS;
    }

    private function fetchFromIcecat(string $brand, string $model): ?string
    {
        $url = 'https://data.icecat.biz/xml_s3/xml_server3.cgi?' . http_build_query([
            'UserName'    => $this->icecatUser,
            'Password'    => $this->icecatPass,
            'Brand'       => $brand,
            'ProductCode' => $model,
            'lang'        => 'ES',
        ]);

        $auth    = base64_encode($this->icecatUser . ':' . $this->icecatPass);
        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => 10,
                'header'        => "User-Agent: Medielectro/1.0\r\nAuthorization: Basic {$auth}",
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) return null;

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($response);
        if ($doc === false) return null;

        if (!isset($doc->Product)) return null;

        $attrs = $doc->Product->attributes();

        if (!empty((string)$attrs['HighPic']))  return (string)$attrs['HighPic'];
        if (!empty((string)$attrs['LowPic']))   return (string)$attrs['LowPic'];
        if (!empty((string)$attrs['ThumbPic'])) return (string)$attrs['ThumbPic'];

        foreach ($doc->Product->ProductGallery->ProductPicture ?? [] as $pic) {
            $picAttrs = $pic->attributes();
            if (!empty((string)$picAttrs['HighPic'])) return (string)$picAttrs['HighPic'];
        }

        return null;
    }
}
