<?php declare(strict_types=1);

namespace Frosh\Jetpack\MailTemplate;

use Frosh\Jetpack\Entity\BundleResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\Language\LanguageEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final class MailTemplateLanguageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly BundleResolver $bundleResolver,
        private readonly MailTemplateSynchronizer $synchronizer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [LanguageEvents::LANGUAGE_WRITTEN_EVENT => 'languageWritten'];
    }

    public function languageWritten(EntityWrittenEvent $event): void
    {
        foreach ($this->bundleResolver->all() as $bundle) {
            if (!is_file($bundle->getPath() . YamlMailTemplateLoader::RELATIVE_PATH)) {
                continue;
            }

            $this->synchronizer->sync($bundle, $event->getContext());
        }
    }
}
