<?php declare(strict_types=1);

namespace Frosh\Jetpack\Migration;

use Frosh\Jetpack\FroshJetpack;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final class MigrationLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MigrationRunner $runner,
        private readonly MigrationProvider $provider,
    ) {
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PluginPreInstallEvent::class => ['install', 1000],
            PluginPreUpdateEvent::class => ['update', 1000],
            PluginPreUninstallEvent::class => ['prepareUninstall', 1000],
            PluginPostUninstallEvent::class => ['uninstall', -1000],
        ];
    }

    public function install(PluginPreInstallEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if ($plugin instanceof FroshJetpack) {
            return;
        }

        $this->runner->up($plugin);
    }

    public function update(PluginPreUpdateEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if ($plugin instanceof FroshJetpack) {
            return;
        }

        $this->runner->up($plugin);
    }

    public function prepareUninstall(PluginPreUninstallEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if ($plugin instanceof FroshJetpack || $event->getContext()->keepUserData()) {
            return;
        }

        $this->provider->forBundle($plugin);
    }

    public function uninstall(PluginPostUninstallEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if ($plugin instanceof FroshJetpack || $event->getContext()->keepUserData()) {
            return;
        }

        $this->runner->down($plugin);
    }
}
