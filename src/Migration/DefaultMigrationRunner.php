<?php declare(strict_types=1);

namespace Frosh\Jetpack\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Bundle;

/**
 * @internal
 */
final class DefaultMigrationRunner implements MigrationRunner
{
    public function __construct(
        private readonly MigrationProvider $provider,
        private readonly MigrationStateStore $stateStore,
        private readonly MigrationLock $lock,
        private readonly Connection $connection,
    ) {
    }

    public function up(Bundle $bundle): array
    {
        return $this->lock->synchronized($bundle->getName(), function () use ($bundle): array {
            $this->stateStore->initialize();
            $migrations = $this->provider->forBundle($bundle);
            $executed = $this->validatedExecutedMigrations($bundle, $migrations);
            $latestApplied = $executed === [] ? null : max(array_map(
                static fn (ExecutedMigration $migration): int => $migration->creationTimestamp,
                $executed,
            ));
            $executedClasses = array_fill_keys(array_map(
                static fn (ExecutedMigration $migration): string => $migration->class,
                $executed,
            ), true);
            $applied = [];
            foreach ($migrations as $migration) {
                $class = $migration::class;
                if (isset($executedClasses[$class])) {
                    continue;
                }
                $timestamp = $migration->getCreationTimestamp();
                if ($latestApplied !== null && $timestamp <= $latestApplied) {
                    throw MigrationException::outOfOrder($bundle->getName(), $class, $timestamp, $latestApplied);
                }

                $migration->up($this->connection);
                $this->stateStore->markExecuted($bundle->getName(), $class, $timestamp);
                $latestApplied = $timestamp;
                $applied[] = $class;
            }

            return $applied;
        });
    }

    public function down(Bundle $bundle): array
    {
        return $this->lock->synchronized($bundle->getName(), function () use ($bundle): array {
            $this->stateStore->initialize();
            $migrations = $this->provider->forBundle($bundle);
            $executed = $this->validatedExecutedMigrations($bundle, $migrations);
            $byClass = [];
            foreach ($migrations as $migration) {
                $byClass[$migration::class] = $migration;
            }
            usort($executed, static fn (ExecutedMigration $first, ExecutedMigration $second): int => $second->creationTimestamp <=> $first->creationTimestamp);

            $removed = [];
            foreach ($executed as $entry) {
                $migration = $byClass[$entry->class];
                $migration->down($this->connection);
                $this->stateStore->remove($bundle->getName(), $entry->class);
                $removed[] = $entry->class;
            }

            return $removed;
        });
    }

    /**
     * @param list<Migration> $migrations
     *
     * @return list<ExecutedMigration>
     */
    private function validatedExecutedMigrations(Bundle $bundle, array $migrations): array
    {
        $available = [];
        foreach ($migrations as $migration) {
            $available[$migration::class] = $migration;
        }

        $executed = $this->stateStore->executed($bundle->getName());
        foreach ($executed as $entry) {
            $migration = $available[$entry->class] ?? null;
            if (!$migration instanceof Migration) {
                throw MigrationException::missingAppliedMigration($bundle->getName(), $entry->class);
            }
            $declared = $migration->getCreationTimestamp();
            if ($declared !== $entry->creationTimestamp) {
                throw MigrationException::changedTimestamp($entry->class, $entry->creationTimestamp, $declared);
            }
        }

        return $executed;
    }
}
