<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\MailTemplate;

use Frosh\Jetpack\MailTemplate\MailTemplateHasher;
use Frosh\Jetpack\MailTemplate\MailTemplateLifecycleSubscriber;
use Frosh\Jetpack\MailTemplate\MailTemplatePayloadCompiler;
use Frosh\Jetpack\MailTemplate\MailTemplateSynchronizer;
use Frosh\Jetpack\MailTemplate\YamlMailTemplateLoader;
use Frosh\Jetpack\Tests\Fixture\InMemoryMailTemplateStore;
use Frosh\Jetpack\Tests\Fixture\RecordingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(MailTemplateLifecycleSubscriber::class)]
final class MailTemplateLifecycleSubscriberTest extends TestCase
{
    public function testSubscribesToPostLifecycleEvents(): void
    {
        static::assertSame([
            PluginPostInstallEvent::class => 'install',
            PluginPostUpdateEvent::class => 'update',
            PluginPostUninstallEvent::class => 'uninstall',
        ], MailTemplateLifecycleSubscriber::getSubscribedEvents());
    }

    public function testInstallSynchronizesAndDestructiveUninstallRemovesTemplates(): void
    {
        $store = $this->store();
        $subscriber = $this->subscriber($store);
        $plugin = new MailTemplateLifecycleTestPlugin(false, 'unused');
        $installContext = new InstallContext(
            $plugin,
            Context::createDefaultContext(),
            '6.6.0.0',
            '1.0.0',
            static::createStub(MigrationCollection::class),
        );

        $subscriber->install(new PluginPostInstallEvent(new PluginEntity(), $installContext));

        static::assertCount(1, $store->writes);
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

        static::assertCount(1, $store->removals);
        static::assertSame([], $store->ownership[$plugin->getName()]);
    }

    public function testKeepUserDataSkipsRemoval(): void
    {
        $store = $this->store();
        $subscriber = $this->subscriber($store);
        $plugin = new MailTemplateLifecycleTestPlugin(false, 'unused');
        $reference = (new MailTemplatePayloadCompiler(new MailTemplateHasher()))->reference(
            $plugin->getName(),
            'acme_order_confirmation',
        );
        $store->ownership[$plugin->getName()][$reference->technicalName] = $reference;
        $context = new UninstallContext(
            $plugin,
            Context::createDefaultContext(),
            '6.7.0.0',
            '1.0.0',
            static::createStub(MigrationCollection::class),
            true,
        );

        $subscriber->uninstall(new PluginPostUninstallEvent(new PluginEntity(), $context));

        static::assertSame([], $store->removals);
        static::assertSame($reference, $store->ownership[$plugin->getName()][$reference->technicalName]);
    }

    private function subscriber(InMemoryMailTemplateStore $store): MailTemplateLifecycleSubscriber
    {
        $definitions = static::createStub(DefinitionInstanceRegistry::class);
        $definitions->method('has')->willReturn(true);
        $hasher = new MailTemplateHasher();

        return new MailTemplateLifecycleSubscriber(new MailTemplateSynchronizer(
            new YamlMailTemplateLoader(new Environment(new ArrayLoader()), $definitions),
            new MailTemplatePayloadCompiler($hasher),
            $hasher,
            $store,
            new RecordingLogger(),
        ));
    }

    private function store(): InMemoryMailTemplateStore
    {
        $store = new InMemoryMailTemplateStore();
        $store->languageRows = [[
            'id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'locale' => 'en-GB',
            'system' => true,
        ]];

        return $store;
    }
}

final class MailTemplateLifecycleTestPlugin extends Plugin
{
    public function getPath(): string
    {
        return \dirname(__DIR__, 2) . '/Fixture/MailTemplateTestBundle';
    }
}
