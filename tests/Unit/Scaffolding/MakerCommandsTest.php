<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Scaffolding;

use Frosh\Jetpack\Attribute\AsScheduledTask;
use Frosh\Jetpack\Command\AbstractMakeCommand;
use Frosh\Jetpack\Command\MakeConsoleCommand;
use Frosh\Jetpack\Command\MakeEntityCommand;
use Frosh\Jetpack\Command\MakeMigrationCommand;
use Frosh\Jetpack\Command\MakeScheduledTaskCommand;
use Frosh\Jetpack\Entity\BundleResolver;
use Frosh\Jetpack\Migration\Migration;
use Frosh\Jetpack\Scaffolding\Generator\CommandScaffolder;
use Frosh\Jetpack\Scaffolding\Generator\EntityScaffolder;
use Frosh\Jetpack\Scaffolding\Generator\MigrationScaffolder;
use Frosh\Jetpack\Scaffolding\Generator\ScheduledTaskScaffolder;
use Frosh\Jetpack\Scaffolding\ScaffoldWriter;
use Frosh\Jetpack\Scaffolding\StubRenderer;
use Frosh\Jetpack\ScheduledTask\ScheduledTaskDeclarationValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

#[CoversClass(AbstractMakeCommand::class)]
#[CoversClass(MakeConsoleCommand::class)]
#[CoversClass(MakeEntityCommand::class)]
#[CoversClass(MakeMigrationCommand::class)]
#[CoversClass(MakeScheduledTaskCommand::class)]
#[CoversClass(CommandScaffolder::class)]
#[CoversClass(EntityScaffolder::class)]
#[CoversClass(MigrationScaffolder::class)]
#[CoversClass(ScheduledTaskScaffolder::class)]
final class MakerCommandsTest extends TestCase
{
    private Filesystem $filesystem;

    private string $temporaryDirectory;

    private ScaffoldTestBundle $bundle;

    private BundleResolver $bundleResolver;

    private ScaffoldWriter $writer;

    private StubRenderer $renderer;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->temporaryDirectory = sys_get_temp_dir() . '/frosh-jetpack-makers-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->temporaryDirectory);
        $this->bundle = new ScaffoldTestBundle($this->temporaryDirectory);
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getBundle')->willReturn($this->bundle);
        $this->bundleResolver = new BundleResolver($kernel);
        $this->writer = new ScaffoldWriter($this->filesystem);
        $this->renderer = new StubRenderer();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->temporaryDirectory);
    }

    public function testMakesEntityWithCanonicalCommandAndLegacyAlias(): void
    {
        $command = new MakeEntityCommand(
            $this->bundleResolver,
            $this->writer,
            new EntityScaffolder($this->renderer),
        );
        $tester = new CommandTester($command);

        static::assertSame('frosh:jetpack:make:entity', $command->getName());
        static::assertContains('frosh:jetpack:entity:make', $command->getAliases());
        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'entity' => 'Review',
            '--table' => 'acme_review_entry',
        ]));

        $directory = $this->temporaryDirectory . '/Entity/Review';
        $files = [
            $directory . '/ReviewEntity.php',
            $directory . '/ReviewCollection.php',
            $directory . '/ReviewDefinition.php',
        ];
        foreach ($files as $file) {
            static::assertFileExists($file);
            static::assertNotEmpty(\PhpToken::tokenize((string) file_get_contents($file), \TOKEN_PARSE));
        }
        static::assertStringContainsString('#[Entity(name: \'acme_review_entry\'', (string) file_get_contents($files[2]));

        static::assertSame(0, $tester->execute(['bundle' => $this->bundle->getName(), 'entity' => 'Review', '--table' => 'acme_review_entry']));
        static::assertStringContainsString('Unchanged', $tester->getDisplay());
    }

    public function testEntityDryRunDoesNotCreateFiles(): void
    {
        $tester = new CommandTester(new MakeEntityCommand(
            $this->bundleResolver,
            $this->writer,
            new EntityScaffolder($this->renderer),
        ));

        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'entity' => 'Review',
            '--dry-run' => true,
        ]));

        static::assertDirectoryDoesNotExist($this->temporaryDirectory . '/Entity');
        static::assertStringContainsString('Would create', $tester->getDisplay());
    }

    public function testMakesTimestampedReversibleMigration(): void
    {
        $clock = static::createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('@1785000123'));
        $tester = new CommandTester(new MakeMigrationCommand(
            $this->bundleResolver,
            $this->writer,
            new MigrationScaffolder($this->renderer),
            $clock,
        ));

        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'migration' => 'AddReviewStatus',
        ]));

        $path = $this->temporaryDirectory . '/Migration/Migration1785000123AddReviewStatus.php';
        static::assertFileExists($path);
        $content = (string) file_get_contents($path);
        static::assertStringContainsString('final class Migration1785000123AddReviewStatus extends Migration', $content);
        static::assertStringContainsString('public function up(Connection $connection): void', $content);
        static::assertStringContainsString('public function down(Connection $connection): void', $content);
        static::assertStringContainsString('return 1785000123;', $content);

        require_once $path;
        $migration = $this->generatedClass(implode('\\', ['Acme', 'Review', 'Migration', 'Migration1785000123AddReviewStatus']))->newInstance();
        static::assertInstanceOf(Migration::class, $migration);
        static::assertSame(1785000123, $migration->getCreationTimestamp());
    }

    public function testMakesOneFileScheduledTaskWithStableName(): void
    {
        $tester = new CommandTester(new MakeScheduledTaskCommand(
            $this->bundleResolver,
            $this->writer,
            new ScheduledTaskScaffolder($this->renderer),
        ));

        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'task' => 'CleanupExpiredReviews',
            '--interval' => '300',
            '--name' => 'acme_review.cleanup_expired_reviews',
            '--reschedule-on-failure' => true,
        ]));

        $path = $this->temporaryDirectory . '/ScheduledTask/CleanupExpiredReviews.php';
        static::assertFileExists($path);
        $content = (string) file_get_contents($path);
        static::assertStringContainsString('interval: 300,', $content);
        static::assertStringContainsString('name: \'acme_review.cleanup_expired_reviews\',', $content);
        static::assertStringContainsString('rescheduleOnFailure: true,', $content);
        static::assertStringContainsString('public function __invoke(Context $context): void', $content);

        require_once $path;
        $reflection = $this->generatedClass(implode('\\', ['Acme', 'Review', 'ScheduledTask', 'CleanupExpiredReviews']));
        $attribute = $reflection->getAttributes(AsScheduledTask::class)[0]->newInstance();
        (new ScheduledTaskDeclarationValidator())->validate($reflection, $attribute, $attribute->name ?? '');
    }

    public function testScheduledTaskMakerDerivesExplicitStableName(): void
    {
        $tester = new CommandTester(new MakeScheduledTaskCommand(
            $this->bundleResolver,
            $this->writer,
            new ScheduledTaskScaffolder($this->renderer),
        ));

        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'task' => 'ImportURLMappings',
        ]));

        $content = (string) file_get_contents($this->temporaryDirectory . '/ScheduledTask/ImportURLMappings.php');
        static::assertStringContainsString('name: \'scaffold_test_bundle.import_url_mappings\',', $content);
        static::assertStringContainsString('rescheduleOnFailure: false,', $content);
    }

    public function testMakesAutoconfiguredSymfonyCommand(): void
    {
        $tester = new CommandTester(new MakeConsoleCommand(
            $this->bundleResolver,
            $this->writer,
            new CommandScaffolder($this->renderer),
        ));

        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'command' => 'RebuildIndex',
            '--name' => 'acme-review:rebuild',
            '--description' => 'Rebuilds Acme\'s review index',
        ]));

        $path = $this->temporaryDirectory . '/Command/RebuildIndexCommand.php';
        static::assertFileExists($path);
        $content = (string) file_get_contents($path);
        static::assertStringContainsString('name: \'acme-review:rebuild\',', $content);
        static::assertStringContainsString('description: \'Rebuilds Acme\\\'s review index\',', $content);
        static::assertStringContainsString('final class RebuildIndexCommand extends Command', $content);
        static::assertStringContainsString('return self::SUCCESS;', $content);

        require_once $path;
        $reflection = $this->generatedClass(implode('\\', ['Acme', 'Review', 'Command', 'RebuildIndexCommand']));
        $attribute = $reflection->getAttributes(AsCommand::class)[0]->newInstance();
        static::assertSame('acme-review:rebuild', $attribute->name);
    }

    public function testSymfonyCommandMakerDerivesNameAndDescription(): void
    {
        $tester = new CommandTester(new MakeConsoleCommand(
            $this->bundleResolver,
            $this->writer,
            new CommandScaffolder($this->renderer),
        ));

        static::assertSame(0, $tester->execute([
            'bundle' => $this->bundle->getName(),
            'command' => 'ImportURLMappingsCommand',
        ]));

        $content = (string) file_get_contents($this->temporaryDirectory . '/Command/ImportURLMappingsCommand.php');
        static::assertStringContainsString('name: \'scaffold-test-bundle:import-url-mappings\',', $content);
        static::assertStringContainsString('description: \'Runs the Import url mappings operation\',', $content);
    }

    public function testRejectsInvalidScheduledTaskInterval(): void
    {
        $tester = new CommandTester(new MakeScheduledTaskCommand(
            $this->bundleResolver,
            $this->writer,
            new ScheduledTaskScaffolder($this->renderer),
        ));

        $this->expectExceptionObject(new \InvalidArgumentException('The scheduled-task interval must be at least 1 second.'));

        $tester->execute([
            'bundle' => $this->bundle->getName(),
            'task' => 'CleanupExpiredReviews',
            '--interval' => '0',
        ]);
    }

    /**
     * @return \ReflectionClass<object>
     */
    private function generatedClass(string $class): \ReflectionClass
    {
        if (!class_exists($class)) {
            throw new \RuntimeException(\sprintf('Generated class "%s" was not loaded.', $class));
        }

        return new \ReflectionClass($class);
    }
}
