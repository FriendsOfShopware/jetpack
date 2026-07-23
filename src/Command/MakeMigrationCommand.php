<?php declare(strict_types=1);

namespace Frosh\Jetpack\Command;

use Frosh\Jetpack\Entity\BundleResolver;
use Frosh\Jetpack\Scaffolding\Generator\MigrationScaffolder;
use Frosh\Jetpack\Scaffolding\ScaffoldWriter;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(name: 'frosh:jetpack:make:migration', description: 'Scaffolds an empty reversible Jetpack migration')]
final class MakeMigrationCommand extends AbstractMakeCommand
{
    public function __construct(
        BundleResolver $bundleResolver,
        ScaffoldWriter $writer,
        private readonly MigrationScaffolder $scaffolder,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($bundleResolver, $writer);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('bundle', InputArgument::REQUIRED, 'Shopware bundle name')
            ->addArgument('migration', InputArgument::REQUIRED, 'Migration suffix in PascalCase');
        $this->addDryRunOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->writePlan(
            $this->scaffolder->create(
                $this->bundle($input),
                $this->requiredStringArgument($input, 'migration'),
                $this->clock->now()->getTimestamp(),
            ),
            $input,
            $output,
        );
    }
}
