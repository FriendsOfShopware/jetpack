<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Migration;

use Frosh\Jetpack\FroshJetpack;
use Frosh\Jetpack\Migration\MigrationLifecycleSubscriber;
use Frosh\Jetpack\Migration\MigrationProvider;
use Frosh\Jetpack\Migration\MigrationRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUpdateEvent;
use Shopware\Core\Framework\Plugin\PluginEntity;

#[CoversClass(MigrationLifecycleSubscriber::class)]
final class MigrationLifecycleSubscriberTest extends TestCase
{
    private LifecycleCallLog $log;

    private MigrationLifecycleSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->log = new LifecycleCallLog();
        $this->subscriber = new MigrationLifecycleSubscriber(
            new RecordingMigrationRunner($this->log),
            new RecordingMigrationProvider($this->log),
        );
    }

    public function testSubscribesBeforeSetupAndLateAfterUninstall(): void
    {
        static::assertSame([
            PluginPreInstallEvent::class => ['install', 1000],
            PluginPreUpdateEvent::class => ['update', 1000],
            PluginPreUninstallEvent::class => ['prepareUninstall', 1000],
            PluginPostUninstallEvent::class => ['uninstall', -1000],
        ], MigrationLifecycleSubscriber::getSubscribedEvents());
    }

    public function testAutomaticallyMigratesOnInstallAndUpdate(): void
    {
        $plugin = new AutomaticMigrationPlugin(false, __DIR__);

        $this->subscriber->install(new PluginPreInstallEvent(new PluginEntity(), $this->installContext($plugin)));
        $this->subscriber->update(new PluginPreUpdateEvent(new PluginEntity(), $this->updateContext($plugin)));

        static::assertSame([
            'up:' . AutomaticMigrationPlugin::class,
            'up:' . AutomaticMigrationPlugin::class,
        ], $this->log->calls);
    }

    public function testPreloadsBeforeAndRollsBackAfterPluginUninstall(): void
    {
        $plugin = new AutomaticMigrationPlugin(false, __DIR__);
        $context = $this->uninstallContext($plugin, false);

        $this->subscriber->prepareUninstall(new PluginPreUninstallEvent(new PluginEntity(), $context));
        $this->subscriber->uninstall(new PluginPostUninstallEvent(new PluginEntity(), $context));

        static::assertSame([
            'prepare:' . AutomaticMigrationPlugin::class,
            'down:' . AutomaticMigrationPlugin::class,
        ], $this->log->calls);
    }

    public function testKeepUserDataSkipsUninstallMigrationWork(): void
    {
        $plugin = new AutomaticMigrationPlugin(false, __DIR__);
        $context = $this->uninstallContext($plugin, true);

        $this->subscriber->prepareUninstall(new PluginPreUninstallEvent(new PluginEntity(), $context));
        $this->subscriber->uninstall(new PluginPostUninstallEvent(new PluginEntity(), $context));

        static::assertSame([], $this->log->calls);
    }

    public function testFroshJetpackSelfMigrationIsNotExecutedTwice(): void
    {
        $plugin = new FroshJetpack(false, __DIR__);
        $uninstallContext = $this->uninstallContext($plugin, false);

        $this->subscriber->install(new PluginPreInstallEvent(new PluginEntity(), $this->installContext($plugin)));
        $this->subscriber->update(new PluginPreUpdateEvent(new PluginEntity(), $this->updateContext($plugin)));
        $this->subscriber->prepareUninstall(new PluginPreUninstallEvent(new PluginEntity(), $uninstallContext));
        $this->subscriber->uninstall(new PluginPostUninstallEvent(new PluginEntity(), $uninstallContext));

        static::assertSame([], $this->log->calls);
    }

    private function installContext(Plugin $plugin): InstallContext
    {
        return new InstallContext(
            $plugin,
            Context::createDefaultContext(),
            '6.7.0.0',
            '1.0.0',
            static::createStub(MigrationCollection::class),
        );
    }

    private function updateContext(Plugin $plugin): UpdateContext
    {
        return new UpdateContext(
            $plugin,
            Context::createDefaultContext(),
            '6.7.0.0',
            '1.0.0',
            static::createStub(MigrationCollection::class),
            '1.1.0',
        );
    }

    private function uninstallContext(Plugin $plugin, bool $keepUserData): UninstallContext
    {
        return new UninstallContext(
            $plugin,
            Context::createDefaultContext(),
            '6.7.0.0',
            '1.0.0',
            static::createStub(MigrationCollection::class),
            $keepUserData,
        );
    }
}

final class AutomaticMigrationPlugin extends Plugin
{
}

final class LifecycleCallLog
{
    /**
     * @var list<string>
     */
    public array $calls = [];
}

final class RecordingMigrationRunner implements MigrationRunner
{
    public function __construct(private readonly LifecycleCallLog $log)
    {
    }

    public function up(Bundle $bundle): array
    {
        $this->log->calls[] = 'up:' . $bundle::class;

        return [];
    }

    public function down(Bundle $bundle): array
    {
        $this->log->calls[] = 'down:' . $bundle::class;

        return [];
    }
}

/**
 * @internal
 */
final class RecordingMigrationProvider implements MigrationProvider
{
    public function __construct(private readonly LifecycleCallLog $log)
    {
    }

    public function forBundle(Bundle $bundle): array
    {
        $this->log->calls[] = 'prepare:' . $bundle::class;

        return [];
    }
}
