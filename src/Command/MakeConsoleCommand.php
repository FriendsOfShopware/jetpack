<?php declare(strict_types=1);

namespace Frosh\Jetpack\Command;

use Frosh\Jetpack\Entity\BundleResolver;
use Frosh\Jetpack\Scaffolding\Generator\CommandScaffolder;
use Frosh\Jetpack\Scaffolding\ScaffoldWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(name: 'frosh:jetpack:make:command', description: 'Scaffolds an autoconfigured Symfony console command')]
final class MakeConsoleCommand extends AbstractMakeCommand
{
    public function __construct(
        BundleResolver $bundleResolver,
        ScaffoldWriter $writer,
        private readonly CommandScaffolder $scaffolder,
    ) {
        parent::__construct($bundleResolver, $writer);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('bundle', InputArgument::REQUIRED, 'Shopware bundle name')
            ->addArgument('command', InputArgument::REQUIRED, 'Command class prefix in PascalCase')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Explicit colon-separated console command name')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Console command description');
        $this->addDryRunOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->writePlan(
            $this->scaffolder->create(
                $this->bundle($input),
                $this->requiredStringArgument($input, 'command'),
                $this->optionalStringOption($input, 'name'),
                $this->optionalStringOption($input, 'description'),
            ),
            $input,
            $output,
        );
    }
}
