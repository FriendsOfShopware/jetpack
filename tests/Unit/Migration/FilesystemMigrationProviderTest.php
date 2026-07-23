<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Migration;

use Frosh\Jetpack\Migration\FilesystemMigrationProvider;
use Frosh\Jetpack\Tests\Fixture\Migration\Migration100First;
use Frosh\Jetpack\Tests\Fixture\Migration\Migration200Second;
use Frosh\Jetpack\Tests\Fixture\MigrationTestBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;

#[CoversClass(FilesystemMigrationProvider::class)]
final class FilesystemMigrationProviderTest extends TestCase
{
    public function testDiscoversOnlyJetpackMigrationsInTimestampOrder(): void
    {
        $migrations = (new FilesystemMigrationProvider())->forBundle(new MigrationTestBundle());

        static::assertSame(
            [Migration100First::class, Migration200Second::class],
            array_map(static fn ($migration): string => $migration::class, $migrations),
        );
    }

    public function testCachesMigrationsForTheSameBundleInstance(): void
    {
        $bundle = new CountingMigrationTestBundle();
        $provider = new FilesystemMigrationProvider();

        $first = $provider->forBundle($bundle);
        $second = $provider->forBundle($bundle);

        static::assertSame($first, $second);
        static::assertSame(1, $bundle->pathReads);
    }
}

final class CountingMigrationTestBundle extends Bundle
{
    public int $pathReads = 0;

    public function getMigrationNamespace(): string
    {
        return 'Frosh\\Jetpack\\Tests\\Fixture\\Migration';
    }

    public function getMigrationPath(): string
    {
        ++$this->pathReads;

        return \dirname(__DIR__, 2) . '/Fixture/Migration';
    }
}
