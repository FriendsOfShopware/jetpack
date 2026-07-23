<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Scaffolding;

use Frosh\Jetpack\Scaffolding\GeneratedFile;
use Frosh\Jetpack\Scaffolding\ScaffoldPlan;
use Frosh\Jetpack\Scaffolding\ScaffoldWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(GeneratedFile::class)]
#[CoversClass(ScaffoldPlan::class)]
#[CoversClass(ScaffoldWriter::class)]
final class ScaffoldWriterTest extends TestCase
{
    private Filesystem $filesystem;

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->temporaryDirectory = sys_get_temp_dir() . '/frosh-jetpack-scaffold-writer-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->temporaryDirectory);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->temporaryDirectory);
    }

    public function testWritesAtomicallyAndTreatsMatchingFilesAsUnchanged(): void
    {
        $writer = new ScaffoldWriter($this->filesystem);
        $plan = new ScaffoldPlan(
            $this->temporaryDirectory,
            [
                new GeneratedFile('One.php', "<?php declare(strict_types=1);\n\nfinal class One\n{\n}\n"),
                new GeneratedFile('Nested/Two.php', "<?php declare(strict_types=1);\n\nfinal class Two\n{\n}\n"),
            ],
            'Created two files.',
        );

        $result = $writer->write($plan, false);

        static::assertCount(2, $result->created);
        static::assertSame([], $result->unchanged);
        static::assertFileExists($this->temporaryDirectory . '/One.php');
        static::assertFileExists($this->temporaryDirectory . '/Nested/Two.php');

        $result = $writer->write($plan, false);

        static::assertSame([], $result->created);
        static::assertCount(2, $result->unchanged);
    }

    public function testDryRunReturnsThePlanWithoutWriting(): void
    {
        $plan = new ScaffoldPlan(
            $this->temporaryDirectory,
            [new GeneratedFile('Planned.php', "<?php declare(strict_types=1);\n")],
            'Created a file.',
        );

        $result = (new ScaffoldWriter($this->filesystem))->write($plan, true);

        static::assertSame([$this->temporaryDirectory . '/Planned.php'], $result->created);
        static::assertSame([], $result->unchanged);
        static::assertFileDoesNotExist($this->temporaryDirectory . '/Planned.php');
    }

    public function testConflictFailsBeforeAnyFileIsWritten(): void
    {
        $this->filesystem->dumpFile($this->temporaryDirectory . '/Existing.php', '<?php // user content');
        $plan = new ScaffoldPlan(
            $this->temporaryDirectory,
            [
                new GeneratedFile('New.php', "<?php declare(strict_types=1);\n"),
                new GeneratedFile('Existing.php', "<?php declare(strict_types=1);\n"),
            ],
            'Created files.',
        );

        $this->expectExceptionObject(new \RuntimeException(\sprintf(
            'Refusing to overwrite existing file "%s" because its content differs.',
            $this->temporaryDirectory . '/Existing.php',
        )));

        try {
            (new ScaffoldWriter($this->filesystem))->write($plan, false);
        } finally {
            static::assertFileDoesNotExist($this->temporaryDirectory . '/New.php');
        }
    }

    public function testInvalidPhpFailsBeforeWriting(): void
    {
        $plan = new ScaffoldPlan(
            $this->temporaryDirectory,
            [new GeneratedFile('Broken.php', '<?php final class')],
            'Created invalid PHP.',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Generated PHP file "Broken.php" is invalid:');

        try {
            (new ScaffoldWriter($this->filesystem))->write($plan, false);
        } finally {
            static::assertFileDoesNotExist($this->temporaryDirectory . '/Broken.php');
        }
    }

    public function testRemovesFilesCreatedBeforeAWriteFailure(): void
    {
        $filesystem = new FailingScaffoldFilesystem();
        $plan = new ScaffoldPlan(
            $this->temporaryDirectory,
            [
                new GeneratedFile('First.php', "<?php declare(strict_types=1);\n"),
                new GeneratedFile('Second.php', "<?php declare(strict_types=1);\n"),
            ],
            'Created files.',
        );

        $this->expectExceptionObject(new \RuntimeException('Simulated write failure.'));

        try {
            (new ScaffoldWriter($filesystem))->write($plan, false);
        } finally {
            static::assertFileDoesNotExist($this->temporaryDirectory . '/First.php');
            static::assertFileDoesNotExist($this->temporaryDirectory . '/Second.php');
        }
    }

    public function testRejectsUnsafeAndDuplicatePaths(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Generated file path must stay below the scaffold root.'));

        new GeneratedFile('../Outside.php', "<?php\n");
    }

    public function testRejectsDuplicatePlanPaths(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Generated file path "Duplicate.php" is duplicated.'));

        new ScaffoldPlan(
            $this->temporaryDirectory,
            [
                new GeneratedFile('Duplicate.php', "<?php declare(strict_types=1);\n"),
                new GeneratedFile('Duplicate.php', "<?php declare(strict_types=1);\n"),
            ],
            'Invalid duplicate plan.',
        );
    }

    public function testRejectsEmptyGeneratedContent(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Generated file content must not be empty.'));

        new GeneratedFile('Empty.php', '');
    }

    public function testRefusesToWriteThroughSymlinkedDirectory(): void
    {
        $outside = $this->temporaryDirectory . '-outside';
        $this->filesystem->mkdir($outside);
        $this->filesystem->symlink($outside, $this->temporaryDirectory . '/Linked');
        $plan = new ScaffoldPlan(
            $this->temporaryDirectory,
            [new GeneratedFile('Linked/Outside.php', "<?php declare(strict_types=1);\n")],
            'Unsafe symlink plan.',
        );

        $this->expectExceptionObject(new \RuntimeException(\sprintf(
            'Refusing to write through symbolic-link directory "%s".',
            $this->temporaryDirectory . '/Linked',
        )));

        try {
            (new ScaffoldWriter($this->filesystem))->write($plan, false);
        } finally {
            static::assertFileDoesNotExist($outside . '/Outside.php');
            $this->filesystem->remove($outside);
        }
    }
}

final class FailingScaffoldFilesystem extends Filesystem
{
    private int $writes = 0;

    public function dumpFile(string $filename, $content): void
    {
        ++$this->writes;
        if ($this->writes === 2) {
            throw new \RuntimeException('Simulated write failure.');
        }

        parent::dumpFile($filename, $content);
    }
}
