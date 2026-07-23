<?php declare(strict_types=1);

namespace Frosh\Jetpack\Command;

use Frosh\Jetpack\Entity\BundleResolver;
use Frosh\Jetpack\Scaffolding\Generator\EntityScaffolder;
use Frosh\Jetpack\Scaffolding\ScaffoldWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'frosh:jetpack:make:entity',
    description: 'Scaffolds a concrete Jetpack entity, definition and typed collection',
    aliases: ['frosh:jetpack:entity:make'],
)]
final class MakeEntityCommand extends AbstractMakeCommand
{
    public function __construct(
        BundleResolver $bundleResolver,
        ScaffoldWriter $writer,
        private readonly EntityScaffolder $scaffolder,
    ) {
        parent::__construct($bundleResolver, $writer);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('bundle', InputArgument::REQUIRED, 'Shopware bundle name')
            ->addArgument('entity', InputArgument::REQUIRED, 'Entity class prefix in PascalCase')
            ->addOption('table', null, InputOption::VALUE_REQUIRED, 'Explicit database table/entity name');
        $this->addDryRunOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->writePlan(
            $this->scaffolder->create(
                $this->bundle($input),
                $this->requiredStringArgument($input, 'entity'),
                $this->optionalStringOption($input, 'table'),
            ),
            $input,
            $output,
        );
    }
}
