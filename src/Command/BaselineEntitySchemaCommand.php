<?php declare(strict_types=1);

namespace Frosh\Jetpack\Command;

use Frosh\Jetpack\Entity\BundleResolver;
use Frosh\Jetpack\Entity\Migration\EntityMigrationCoordinator;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(name: 'frosh:jetpack:entity:baseline', description: 'Records the current entity schema without generating a migration or querying the database')]
final class BaselineEntitySchemaCommand extends AbstractEntityCommand
{
    public function __construct(
        BundleResolver $bundleResolver,
        private readonly EntityMigrationCoordinator $coordinator,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($bundleResolver);
    }

    protected function configure(): void
    {
        $this->addArgument('bundle', InputArgument::REQUIRED, 'Shopware bundle name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->coordinator->baseline($this->bundle($input), $this->clock->now()->getTimestamp());
        $io->success(\sprintf('Created baseline snapshot %s. No database was inspected or changed.', $result->snapshot?->id));

        return Command::SUCCESS;
    }
}
