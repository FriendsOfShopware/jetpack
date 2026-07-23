<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\CustomField;

use Frosh\Jetpack\CustomField\CustomFieldLifecycleSubscriber;
use Frosh\Jetpack\CustomField\CustomFieldPayloadCompiler;
use Frosh\Jetpack\CustomField\CustomFieldSynchronizer;
use Frosh\Jetpack\CustomField\YamlCustomFieldLoader;
use Frosh\Jetpack\FroshJetpack;
use Frosh\Jetpack\Tests\Fixture\CustomFieldTestBundle;
use Frosh\Jetpack\Tests\Fixture\InMemoryCustomFieldSetStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopware\Core\Framework\Plugin\PluginEntity;

#[CoversClass(CustomFieldLifecycleSubscriber::class)]
final class CustomFieldLifecycleSubscriberTest extends TestCase
{
    public function testSubscribesToPostLifecycleEvents(): void
    {
        static::assertSame([
            PluginPostInstallEvent::class => 'install',
            PluginPostUpdateEvent::class => 'update',
            PluginPostUninstallEvent::class => 'uninstall',
        ], CustomFieldLifecycleSubscriber::getSubscribedEvents());
    }

    public function testInstallSynchronizesAndDestructiveUninstallRemovesDefinitions(): void
    {
        $store = new InMemoryCustomFieldSetStore();
        $subscriber = $this->subscriber($store);
        $plugin = new CustomFieldLifecycleTestPlugin(false, (new CustomFieldTestBundle())->getPath());
        $context = new InstallContext(
            $plugin,
            Context::createDefaultContext(),
            '6.6.0.0',
            '1.0.0',
            static::createStub(MigrationCollection::class),
        );

        $subscriber->install(new PluginPostInstallEvent(new PluginEntity(), $context));

        static::assertCount(1, $store->upserts);
        static::assertCount(1, $store->ownership[$plugin->getName()]);

        $uninstallContext = new UninstallContext(
            $plugin,
            Context::createDefaultContext(),
            '6.6.0.0',
            '1.0.0',
            static::createStub(MigrationCollection::class),
            false,
        );
        $subscriber->uninstall(new PluginPostUninstallEvent(new PluginEntity(), $uninstallContext));

        static::assertSame([], $store->ownership[$plugin->getName()]);
        static::assertCount(1, $store->deletedSets);
    }

    public function testKeepUserDataSkipsRemoval(): void
    {
        $store = new InMemoryCustomFieldSetStore();
        $subscriber = $this->subscriber($store);
        $plugin = new CustomFieldLifecycleTestPlugin(false, (new CustomFieldTestBundle())->getPath());
        $setId = '01911111111111111111111111111111';
        $store->ownership[$plugin->getName()] = ['acme_product_details' => $setId];
        $context = new UninstallContext(
            $plugin,
            Context::createDefaultContext(),
            '6.7.0.0',
            '1.0.0',
            static::createStub(MigrationCollection::class),
            true,
        );

        $subscriber->uninstall(new PluginPostUninstallEvent(new PluginEntity(), $context));

        static::assertSame(['acme_product_details' => $setId], $store->ownership[$plugin->getName()]);
        static::assertSame([], $store->deletedSets);
    }

    public function testSkipsFroshJetpackSelfLifecycle(): void
    {
        $store = new InMemoryCustomFieldSetStore();
        $subscriber = $this->subscriber($store);
        $plugin = new FroshJetpack(false, \dirname(__DIR__, 3));
        $context = new InstallContext(
            $plugin,
            Context::createDefaultContext(),
            '6.7.0.0',
            '1.0.0',
            static::createStub(MigrationCollection::class),
        );

        $subscriber->install(new PluginPostInstallEvent(new PluginEntity(), $context));

        static::assertSame([], $store->ownership);
        static::assertSame([], $store->upserts);
    }

    private function subscriber(InMemoryCustomFieldSetStore $store): CustomFieldLifecycleSubscriber
    {
        return new CustomFieldLifecycleSubscriber(new CustomFieldSynchronizer(
            new YamlCustomFieldLoader(),
            new CustomFieldPayloadCompiler(),
            $store,
        ));
    }
}

final class CustomFieldLifecycleTestPlugin extends Plugin
{
    public function getPath(): string
    {
        return \dirname(__DIR__, 2) . '/Fixture/CustomFieldTestBundle';
    }
}
