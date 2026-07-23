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
#[AsCommand(name: 'frosh:jetpack:entity:diff', description: 'Shows code-first entity schema changes without querying the database')]
final class DiffEntitySchemaCommand extends AbstractEntityCommand
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
        $bundle = $this->bundle($input);
        $plan = $this->coordinator->plan($bundle);
        if ($plan->isEmpty()) {
            if ($plan->hasDeclarationChanges() || !$this->coordinator->isCurrent($bundle)) {
                $io->note('Only runtime entity metadata changed. Generate a snapshot-only journal entry; no database migration is required.');
            } else {
                $io->success('The entity declarations match the latest snapshot.');
            }

            return Command::SUCCESS;
        }
        $rows = [];
        foreach ($plan->operations as $operation) {
            $rows[] = [$operation->destructive ? 'destructive' : 'safe', $operation->description()];
        }
        $io->table(['Phase', 'Change'], $rows);

        return Command::SUCCESS;
    }
}
