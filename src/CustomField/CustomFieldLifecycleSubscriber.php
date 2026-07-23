<?php declare(strict_types=1);

namespace Frosh\Jetpack\CustomField;

use Frosh\Jetpack\FroshJetpack;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final class CustomFieldLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly CustomFieldSynchronizer $synchronizer)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostInstallEvent::class => 'install',
            PluginPostUpdateEvent::class => 'update',
            PluginPostUninstallEvent::class => 'uninstall',
        ];
    }

    public function install(PluginPostInstallEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if ($plugin instanceof FroshJetpack) {
            return;
        }

        $this->synchronizer->sync($plugin, $event->getContext()->getContext());
    }

    public function update(PluginPostUpdateEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if ($plugin instanceof FroshJetpack) {
            return;
        }

        $this->synchronizer->sync($plugin, $event->getContext()->getContext());
    }

    public function uninstall(PluginPostUninstallEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if ($plugin instanceof FroshJetpack || $event->getContext()->keepUserData()) {
            return;
        }

        $this->synchronizer->remove($plugin, $event->getContext()->getContext());
    }
}
