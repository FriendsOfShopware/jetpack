<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Migration;

use Frosh\Jetpack\Entity\BundleEntitySchemaProvider;
use Frosh\Jetpack\Entity\Schema\BundleSchema;
use Frosh\Jetpack\Entity\Snapshot\SchemaSnapshotStore;
use Shopware\Core\Framework\Bundle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
final class EntityMigrationCoordinator
{
    public function __construct(
        private readonly BundleEntitySchemaProvider $schemaProvider,
        private readonly SchemaSnapshotStore $snapshotStore,
        private readonly SchemaDiffer $differ,
        private readonly MigrationPlanInverter $inverter,
        private readonly MigrationStepRenderer $renderer,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function current(Bundle $bundle): BundleSchema
    {
        return $this->schemaProvider->forBundle($bundle);
    }

    public function plan(Bundle $bundle): MigrationPlan
    {
        $current = $this->current($bundle);
        $latest = $this->snapshotStore->latest($bundle);
        $previous = $latest === null ? BundleSchema::empty($bundle->getName()) : $latest->schema;

        return $this->differ->diff($previous, $current);
    }

    public function generate(
        Bundle $bundle,
        int $timestamp,
        string $name,
        bool $allowDestructive,
    ): GenerationResult {
        $schema = $this->current($bundle);
        $latest = $this->snapshotStore->latest($bundle);
        $previous = $latest === null ? BundleSchema::empty($bundle->getName()) : $latest->schema;
        $plan = $this->differ->diff($previous, $schema);
        if ($plan->isEmpty()) {
            if ($latest !== null && $latest->schema->fingerprint() === $schema->fingerprint()) {
                return new GenerationResult($plan, null, null);
            }
            $snapshot = $this->snapshotStore->append($bundle, $schema, $timestamp, $name, null);

            return new GenerationResult($plan, null, $snapshot);
        }
        if (!$allowDestructive && $plan->destructive() !== []) {
            throw new \RuntimeException('The entity schema contains destructive operations. Review the diff and rerun with --allow-destructive.');
        }

        $this->assertMigrationTimestampAvailable($bundle, $timestamp);
        $rendered = $this->renderer->render(
            $bundle->getMigrationNamespace(),
            $timestamp,
            $name,
            $plan,
            $this->inverter->invert($plan),
            $schema,
            $previous,
        );
        $migrationPath = Path::join($bundle->getMigrationPath(), $rendered->className . '.php');
        if (is_file($migrationPath)) {
            throw new \RuntimeException(\sprintf('Migration file "%s" already exists.', $migrationPath));
        }
        $this->filesystem->mkdir($bundle->getMigrationPath());
        $this->filesystem->dumpFile($migrationPath, $rendered->content);
        try {
            $snapshot = $this->snapshotStore->append($bundle, $schema, $timestamp, $name, $rendered->className);
        } catch (\Throwable $exception) {
            $this->filesystem->remove($migrationPath);

            throw $exception;
        }

        return new GenerationResult($plan, $migrationPath, $snapshot);
    }

    public function baseline(Bundle $bundle, int $timestamp, string $name = 'baseline'): GenerationResult
    {
        if ($this->snapshotStore->latest($bundle) !== null) {
            throw new \RuntimeException(\sprintf('Bundle "%s" already has an entity schema snapshot.', $bundle->getName()));
        }
        $schema = $this->current($bundle);
        $snapshot = $this->snapshotStore->append($bundle, $schema, $timestamp, $name, null);

        return new GenerationResult(new MigrationPlan([]), null, $snapshot);
    }

    public function isCurrent(Bundle $bundle): bool
    {
        $current = $this->current($bundle);
        $latest = $this->snapshotStore->latest($bundle);

        return $latest !== null && $latest->schema->fingerprint() === $current->fingerprint();
    }

    private function assertMigrationTimestampAvailable(Bundle $bundle, int $timestamp): void
    {
        $directory = $bundle->getMigrationPath();
        if (!is_dir($directory)) {
            return;
        }
        $entries = scandir($directory);
        if ($entries === false) {
            throw new \RuntimeException(\sprintf('Cannot read migration directory "%s".', $directory));
        }
        $prefix = 'Migration' . $timestamp;
        foreach ($entries as $entry) {
            if (str_starts_with($entry, $prefix) && str_ends_with($entry, '.php')) {
                throw new \RuntimeException(\sprintf('Migration timestamp %d is already used by "%s". Retry after the clock advances.', $timestamp, $entry));
            }
        }
    }
}
