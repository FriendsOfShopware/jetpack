<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Entity;

use Frosh\Jetpack\Entity\Schema\BundleSchema;
use Frosh\Jetpack\Entity\Snapshot\SchemaSnapshotStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(SchemaSnapshotStore::class)]
final class SchemaSnapshotStoreTest extends TestCase
{
    private Filesystem $filesystem;

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->temporaryDirectory = sys_get_temp_dir() . '/frosh-jetpack-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->temporaryDirectory);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->temporaryDirectory);
    }

    public function testPersistsAndValidatesTheCompleteSnapshotChain(): void
    {
        $bundle = new SnapshotTestBundle($this->temporaryDirectory);
        $schema = BundleSchema::empty($bundle->getName());
        $store = new SchemaSnapshotStore($this->filesystem);

        $first = $store->append($bundle, $schema, 1_784_682_000, 'baseline', null);
        $second = $store->append($bundle, $schema, 1_784_682_001, 'next', 'Migration1784682001Next');

        static::assertSame($first->id, $second->previousId);
        static::assertSame([$first->id, $second->id], array_map(static fn ($snapshot): string => $snapshot->id, $store->all($bundle)));
        static::assertSame($second->id, $store->latest($bundle)?->id);
    }

    public function testRejectsNonIncreasingTimestamps(): void
    {
        $bundle = new SnapshotTestBundle($this->temporaryDirectory);
        $schema = BundleSchema::empty($bundle->getName());
        $store = new SchemaSnapshotStore($this->filesystem);
        $store->append($bundle, $schema, 1_784_682_000, 'baseline', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must be newer');

        $store->append($bundle, $schema, 1_784_682_000, 'duplicate', null);
    }

    public function testRejectsBrokenSnapshotChain(): void
    {
        $bundle = new SnapshotTestBundle($this->temporaryDirectory);
        $schema = BundleSchema::empty($bundle->getName());
        $store = new SchemaSnapshotStore($this->filesystem);
        $store->append($bundle, $schema, 1_784_682_000, 'baseline', null);
        $second = $store->append($bundle, $schema, 1_784_682_001, 'next', null);
        $path = $store->directory($bundle) . '/' . $second->id . '.json';
        $document = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($document);
        $document['previousId'] = 'tampered';
        $this->filesystem->dumpFile($path, json_encode($document, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('chain is broken');

        $store->latest($bundle);
    }
}

final class SnapshotTestBundle extends Bundle
{
    public function __construct(private readonly string $bundlePath)
    {
    }

    public function getPath(): string
    {
        return $this->bundlePath;
    }
}
