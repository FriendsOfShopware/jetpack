<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Entity;

use Frosh\Jetpack\Entity\BundleEntitySchemaProvider;
use Frosh\Jetpack\Entity\Migration\DefinitionNameResolver;
use Frosh\Jetpack\Entity\Migration\EntityMigrationCoordinator;
use Frosh\Jetpack\Entity\Migration\MigrationPlanInverter;
use Frosh\Jetpack\Entity\Migration\MigrationStepRenderer;
use Frosh\Jetpack\Entity\Migration\MySqlSchemaRenderer;
use Frosh\Jetpack\Entity\Migration\SchemaDiffer;
use Frosh\Jetpack\Entity\Snapshot\SchemaSnapshotStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(EntityMigrationCoordinator::class)]
final class EntityMigrationCoordinatorTest extends TestCase
{
    private Filesystem $filesystem;

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->temporaryDirectory = sys_get_temp_dir() . '/frosh-jetpack-coordinator-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->temporaryDirectory);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->temporaryDirectory);
    }

    public function testRecordsSnapshotOnlyDeclarationChangesWithoutCreatingMigration(): void
    {
        $bundle = new CoordinatorTestBundle($this->temporaryDirectory);
        $snapshotStore = new SchemaSnapshotStore($this->filesystem);
        $coordinator = new EntityMigrationCoordinator(
            new BundleEntitySchemaProvider([]),
            $snapshotStore,
            new SchemaDiffer(),
            new MigrationPlanInverter(),
            new MigrationStepRenderer(new MySqlSchemaRenderer(new DefinitionNameResolver())),
            $this->filesystem,
        );

        $first = $coordinator->generate($bundle, 1_784_682_000, 'empty declaration', false);
        $second = $coordinator->generate($bundle, 1_784_682_001, 'unchanged', false);

        static::assertNull($first->migrationPath);
        static::assertNotNull($first->snapshot);
        static::assertNull($second->migrationPath);
        static::assertNull($second->snapshot);
        static::assertTrue($coordinator->isCurrent($bundle));
    }
}

final class CoordinatorTestBundle extends Bundle
{
    public function __construct(private readonly string $bundlePath)
    {
    }

    public function getPath(): string
    {
        return $this->bundlePath;
    }
}
