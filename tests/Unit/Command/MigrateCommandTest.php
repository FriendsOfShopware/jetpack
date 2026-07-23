<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Command;

use Frosh\Jetpack\Command\MigrateCommand;
use Frosh\Jetpack\Entity\BundleResolver;
use Frosh\Jetpack\Migration\Migration;
use Frosh\Jetpack\Migration\MigrationRunner;
use Frosh\Jetpack\Tests\Fixture\Migration\Migration100First;
use Frosh\Jetpack\Tests\Fixture\Migration\Migration200Second;
use Frosh\Jetpack\Tests\Fixture\MigrationTestBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

#[CoversClass(MigrateCommand::class)]
final class MigrateCommandTest extends TestCase
{
    public function testRequiresEitherAPluginNameOrTheAllOption(): void
    {
        $tester = $this->tester([], new RecordingMigrationRunner());

        $status = $tester->execute([]);

        static::assertSame(Command::INVALID, $status);
        static::assertStringContainsString('Pass a plugin name or use --all.', $tester->getDisplay());
    }

    public function testRejectsAPluginNameTogetherWithTheAllOption(): void
    {
        $plugin = new AlphaMigrationTestPlugin(true, __DIR__);
        $tester = $this->tester([$plugin], new RecordingMigrationRunner());

        $status = $tester->execute(['plugin' => $plugin->getName(), '--all' => true]);

        static::assertSame(Command::INVALID, $status);
        static::assertStringContainsString('Plugin name and --all cannot be used together.', $tester->getDisplay());
    }

    public function testRunsPendingMigrationsForOnePlugin(): void
    {
        $plugin = new AlphaMigrationTestPlugin(true, __DIR__);
        $runner = new RecordingMigrationRunner([
            $plugin->getName() => [Migration100First::class, Migration200Second::class],
        ]);
        $tester = $this->tester([$plugin], $runner);

        $status = $tester->execute(['plugin' => $plugin->getName()]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertSame([$plugin->getName()], $runner->plugins);
        static::assertStringContainsString(Migration100First::class, $tester->getDisplay());
        static::assertStringContainsString(Migration200Second::class, $tester->getDisplay());
        static::assertStringContainsString(
            \sprintf('Applied 2 Jetpack migrations for plugin "%s".', $plugin->getName()),
            $tester->getDisplay(),
        );
    }

    public function testReportsWhenOnePluginIsAlreadyCurrent(): void
    {
        $plugin = new AlphaMigrationTestPlugin(true, __DIR__);
        $runner = new RecordingMigrationRunner();
        $tester = $this->tester([$plugin], $runner);

        $status = $tester->execute(['plugin' => $plugin->getName()]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertSame([$plugin->getName()], $runner->plugins);
        static::assertStringContainsString(
            \sprintf('No pending Jetpack migrations for plugin "%s".', $plugin->getName()),
            $tester->getDisplay(),
        );
    }

    public function testAllRunsOnlyActivePluginsInDeterministicOrder(): void
    {
        $alpha = new AlphaMigrationTestPlugin(true, __DIR__);
        $zulu = new ZuluMigrationTestPlugin(true, __DIR__);
        $runner = new RecordingMigrationRunner([
            $zulu->getName() => [Migration200Second::class],
        ]);
        $tester = $this->tester([$zulu, new MigrationTestBundle(), $alpha], $runner);

        $status = $tester->execute(['--all' => true]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertSame([$alpha->getName(), $zulu->getName()], $runner->plugins);
        static::assertStringContainsString(
            'Applied 1 Jetpack migration across 1 of 2 active plugins.',
            $tester->getDisplay(),
        );
    }

    public function testAllReportsWhenEveryPluginIsAlreadyCurrent(): void
    {
        $plugin = new AlphaMigrationTestPlugin(true, __DIR__);
        $runner = new RecordingMigrationRunner();
        $tester = $this->tester([$plugin], $runner);

        $status = $tester->execute(['--all' => true]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertSame([$plugin->getName()], $runner->plugins);
        static::assertStringContainsString(
            'No pending Jetpack migrations for 1 active plugin.',
            $tester->getDisplay(),
        );
    }

    public function testAllReportsWhenThereAreNoActivePlugins(): void
    {
        $runner = new RecordingMigrationRunner();
        $tester = $this->tester([new MigrationTestBundle()], $runner);

        $status = $tester->execute(['--all' => true]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertSame([], $runner->plugins);
        static::assertStringContainsString('No active Shopware plugins found.', $tester->getDisplay());
    }

    public function testRejectsANonPluginBundle(): void
    {
        $bundle = new MigrationTestBundle();
        $tester = $this->tester([$bundle], new RecordingMigrationRunner());

        $status = $tester->execute(['plugin' => $bundle->getName()]);

        static::assertSame(Command::INVALID, $status);
        static::assertStringContainsString(
            \sprintf('Bundle "%s" is not an active Shopware plugin.', $bundle->getName()),
            $tester->getDisplay(),
        );
    }

    /**
     * @param list<Bundle> $bundles
     */
    private function tester(array $bundles, MigrationRunner $runner): CommandTester
    {
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getBundles')->willReturn($bundles);
        $kernel->method('getBundle')->willReturnCallback(static function (string $name) use ($bundles): Bundle {
            foreach ($bundles as $bundle) {
                if ($bundle->getName() === $name) {
                    return $bundle;
                }
            }

            throw new \InvalidArgumentException(\sprintf('Bundle "%s" does not exist.', $name));
        });

        return new CommandTester(new MigrateCommand(new BundleResolver($kernel), $runner));
    }
}

final class AlphaMigrationTestPlugin extends Plugin
{
}

final class ZuluMigrationTestPlugin extends Plugin
{
}

final class RecordingMigrationRunner implements MigrationRunner
{
    /**
     * @var list<string>
     */
    public array $plugins = [];

    /**
     * @param array<string, list<class-string<Migration>>> $results
     */
    public function __construct(private readonly array $results = [])
    {
    }

    public function up(Bundle $bundle): array
    {
        $this->plugins[] = $bundle->getName();

        return $this->results[$bundle->getName()] ?? [];
    }

    public function down(Bundle $bundle): array
    {
        throw new \LogicException('The migrate command must never run down migrations.');
    }
}
