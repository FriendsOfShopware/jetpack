<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Frosh\Jetpack\Migration\DefaultMigrationRunner;
use Frosh\Jetpack\Migration\ExecutedMigration;
use Frosh\Jetpack\Migration\Migration;
use Frosh\Jetpack\Migration\MigrationException;
use Frosh\Jetpack\Migration\MigrationLock;
use Frosh\Jetpack\Migration\MigrationProvider;
use Frosh\Jetpack\Migration\MigrationStateStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;

#[CoversClass(DefaultMigrationRunner::class)]
final class DefaultMigrationRunnerTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        RunnerMigrationLog::$entries = [];
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    public function testAppliesOnceAndRollsBackInReverseOrder(): void
    {
        $state = new InMemoryMigrationStateStore();
        $runner = $this->runner([new RunnerMigration100(), new RunnerMigration200()], $state);
        $bundle = new RunnerTestBundle();

        static::assertSame([RunnerMigration100::class, RunnerMigration200::class], $runner->up($bundle));
        static::assertSame([], $runner->up($bundle));
        static::assertSame([RunnerMigration200::class, RunnerMigration100::class], $runner->down($bundle));
        static::assertSame(
            ['up:100', 'up:200', 'down:200', 'down:100'],
            RunnerMigrationLog::$entries,
        );
        static::assertSame([], $state->executed($bundle->getName()));
    }

    public function testDoesNotRecordFailingMigration(): void
    {
        $state = new InMemoryMigrationStateStore();
        $runner = $this->runner([new FailingRunnerMigration()], $state);
        $bundle = new RunnerTestBundle();

        try {
            $runner->up($bundle);
            static::fail('Expected the migration to fail.');
        } catch (\RuntimeException $exception) {
            static::assertSame('migration failed', $exception->getMessage());
        }

        static::assertSame([], $state->executed($bundle->getName()));
    }

    public function testRejectsMigrationInsertedBeforeAppliedHistory(): void
    {
        $state = new InMemoryMigrationStateStore();
        $bundle = new RunnerTestBundle();
        $state->markExecuted($bundle->getName(), RunnerMigration200::class, 200);
        $runner = $this->runner([new RunnerMigration100(), new RunnerMigration200()], $state);

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('older than the latest applied migration');

        $runner->up($bundle);
    }

    public function testRejectsMissingAppliedMigrationBeforeRollback(): void
    {
        $state = new InMemoryMigrationStateStore();
        $bundle = new RunnerTestBundle();
        $state->markExecuted($bundle->getName(), RunnerMigration100::class, 100);
        $runner = $this->runner([], $state);

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('is no longer available');

        $runner->down($bundle);
    }

    /**
     * @param list<Migration> $migrations
     */
    private function runner(array $migrations, InMemoryMigrationStateStore $state): DefaultMigrationRunner
    {
        return new DefaultMigrationRunner(
            new FixedMigrationProvider($migrations),
            $state,
            new ImmediateMigrationLock(),
            $this->connection,
        );
    }
}

final class RunnerTestBundle extends Bundle
{
}

final class RunnerMigrationLog
{
    /**
     * @var list<string>
     */
    public static array $entries = [];
}

final class RunnerMigration100 extends Migration
{
    public function getCreationTimestamp(): int
    {
        return 100;
    }

    public function up(Connection $connection): void
    {
        RunnerMigrationLog::$entries[] = 'up:100';
    }

    public function down(Connection $connection): void
    {
        RunnerMigrationLog::$entries[] = 'down:100';
    }
}

final class RunnerMigration200 extends Migration
{
    public function getCreationTimestamp(): int
    {
        return 200;
    }

    public function up(Connection $connection): void
    {
        RunnerMigrationLog::$entries[] = 'up:200';
    }

    public function down(Connection $connection): void
    {
        RunnerMigrationLog::$entries[] = 'down:200';
    }
}

final class FailingRunnerMigration extends Migration
{
    public function getCreationTimestamp(): int
    {
        return 300;
    }

    public function up(Connection $connection): void
    {
        throw new \RuntimeException('migration failed');
    }

    public function down(Connection $connection): void
    {
    }
}

/**
 * @internal
 */
final class FixedMigrationProvider implements MigrationProvider
{
    /**
     * @param list<Migration> $migrations
     */
    public function __construct(private readonly array $migrations)
    {
    }

    public function forBundle(Bundle $bundle): array
    {
        return $this->migrations;
    }
}

/**
 * @internal
 */
final class InMemoryMigrationStateStore implements MigrationStateStore
{
    /**
     * @var array<string, list<ExecutedMigration>>
     */
    private array $executed = [];

    public function initialize(): void
    {
    }

    public function executed(string $bundle): array
    {
        return $this->executed[$bundle] ?? [];
    }

    public function markExecuted(string $bundle, string $class, int $creationTimestamp): void
    {
        $this->executed[$bundle][] = new ExecutedMigration($class, $creationTimestamp);
    }

    public function remove(string $bundle, string $class): void
    {
        $this->executed[$bundle] = array_values(array_filter(
            $this->executed[$bundle] ?? [],
            static fn (ExecutedMigration $migration): bool => $migration->class !== $class,
        ));
    }
}

/**
 * @internal
 */
final class ImmediateMigrationLock implements MigrationLock
{
    public function synchronized(string $bundle, \Closure $callback): mixed
    {
        return $callback();
    }
}
