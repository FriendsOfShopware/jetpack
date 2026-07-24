<?php declare(strict_types=1);

namespace Frosh\Jetpack\Command;

use Frosh\Jetpack\Entity\BundleResolver;
use Frosh\Jetpack\Scaffolding\Generator\CmsElementScaffolder;
use Frosh\Jetpack\Scaffolding\ScaffoldWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(name: 'frosh:jetpack:make:cms-element', description: 'Scaffolds an entity-backed declarative CMS element')]
final class MakeCmsElementCommand extends AbstractMakeCommand
{
    public function __construct(
        BundleResolver $bundleResolver,
        ScaffoldWriter $writer,
        private readonly CmsElementScaffolder $scaffolder,
    ) {
        parent::__construct($bundleResolver, $writer);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('bundle', InputArgument::REQUIRED, 'Shopware bundle name')
            ->addArgument('element', InputArgument::REQUIRED, 'CMS element class prefix in PascalCase')
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'DAL entity technical name')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Explicit lower-kebab CMS element name')
            ->addOption('field', null, InputOption::VALUE_REQUIRED, 'CMS config field in lowerCamelCase')
            ->addOption('definition', null, InputOption::VALUE_REQUIRED, 'Entity definition class used by the Storefront resolver')
            ->addOption('label-property', null, InputOption::VALUE_REQUIRED, 'Entity property displayed by the selector and starter template', 'name');
        $this->addDryRunOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $entity = $this->optionalStringOption($input, 'entity');
        if ($entity === null) {
            throw new \InvalidArgumentException('The --entity option is required.');
        }

        return $this->writePlan(
            $this->scaffolder->create(
                $this->bundle($input),
                $this->requiredStringArgument($input, 'element'),
                $entity,
                $this->optionalStringOption($input, 'name'),
                $this->optionalStringOption($input, 'field'),
                $this->optionalStringOption($input, 'definition'),
                $this->optionalStringOption($input, 'label-property') ?? 'name',
            ),
            $input,
            $output,
        );
    }
}
