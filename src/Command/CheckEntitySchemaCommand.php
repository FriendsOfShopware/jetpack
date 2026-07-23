<?php declare(strict_types=1);

namespace Frosh\Jetpack\Command;

use Frosh\Jetpack\Entity\BundleResolver;
use Frosh\Jetpack\Entity\Migration\EntityMigrationCoordinator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(name: 'frosh:jetpack:entity:check', description: 'Fails when entity declarations differ from their latest committed snapshot')]
final class CheckEntitySchemaCommand extends AbstractEntityCommand
{
    public function __construct(BundleResolver $bundleResolver, private readonly EntityMigrationCoordinator $coordinator)
    {
        parent::__construct($bundleResolver);
    }

    protected function configure(): void
    {
        $this->addArgument('bundle', InputArgument::REQUIRED, 'Shopware bundle name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->coordinator->isCurrent($this->bundle($input))) {
            $io->error('Entity schema drift detected. Generate and commit a migration and snapshot.');

            return Command::FAILURE;
        }
        $io->success('Entity schema snapshot is current.');

        return Command::SUCCESS;
    }
}
