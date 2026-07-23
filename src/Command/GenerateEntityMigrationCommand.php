<?php declare(strict_types=1);

namespace Frosh\Jetpack\Command;

use Frosh\Jetpack\Entity\BundleResolver;
use Frosh\Jetpack\Entity\Migration\EntityMigrationCoordinator;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(name: 'frosh:jetpack:entity:generate', description: 'Generates a reversible Jetpack migration by comparing entity schema snapshots')]
final class GenerateEntityMigrationCommand extends AbstractEntityCommand
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
        $this
            ->addArgument('bundle', InputArgument::REQUIRED, 'Shopware bundle name')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Migration name', 'entity_schema')
            ->addOption('allow-destructive', null, InputOption::VALUE_NONE, 'Allow destructive operations in the generated migration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getOption('name');
        if (!\is_string($name) || trim($name) === '') {
            throw new \InvalidArgumentException('The migration name must be a non-empty string.');
        }
        $result = $this->coordinator->generate(
            $this->bundle($input),
            $this->clock->now()->getTimestamp(),
            $name,
            (bool) $input->getOption('allow-destructive'),
        );
        if ($result->migrationPath === null) {
            $io->success($result->snapshot === null
                ? 'No entity declaration changes detected.'
                : \sprintf('Updated entity snapshot %s. No database migration was required.', $result->snapshot->id));

            return Command::SUCCESS;
        }
        $io->success(\sprintf('Created migration %s and snapshot %s.', $result->migrationPath, $result->snapshot?->id));

        return Command::SUCCESS;
    }
}
