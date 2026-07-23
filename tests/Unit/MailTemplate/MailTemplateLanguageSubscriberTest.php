<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\MailTemplate;

use Frosh\Jetpack\Entity\BundleResolver;
use Frosh\Jetpack\MailTemplate\MailTemplateHasher;
use Frosh\Jetpack\MailTemplate\MailTemplateLanguageSubscriber;
use Frosh\Jetpack\MailTemplate\MailTemplatePayloadCompiler;
use Frosh\Jetpack\MailTemplate\MailTemplateSynchronizer;
use Frosh\Jetpack\MailTemplate\YamlMailTemplateLoader;
use Frosh\Jetpack\Tests\Fixture\InMemoryMailTemplateStore;
use Frosh\Jetpack\Tests\Fixture\MailTemplateTestBundle;
use Frosh\Jetpack\Tests\Fixture\RecordingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\Language\LanguageEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(MailTemplateLanguageSubscriber::class)]
final class MailTemplateLanguageSubscriberTest extends TestCase
{
    public function testNewLanguageSynchronizesAllActiveManifests(): void
    {
        static::assertSame([
            LanguageEvents::LANGUAGE_WRITTEN_EVENT => 'languageWritten',
        ], MailTemplateLanguageSubscriber::getSubscribedEvents());

        $bundle = new MailTemplateTestBundle();
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getBundles')->willReturn([$bundle]);
        $store = new InMemoryMailTemplateStore();
        $store->languageRows = [[
            'id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'locale' => 'en-GB',
            'system' => true,
        ]];
        $definitions = static::createStub(DefinitionInstanceRegistry::class);
        $definitions->method('has')->willReturn(true);
        $hasher = new MailTemplateHasher();
        $subscriber = new MailTemplateLanguageSubscriber(
            new BundleResolver($kernel),
            new MailTemplateSynchronizer(
                new YamlMailTemplateLoader(new Environment(new ArrayLoader()), $definitions),
                new MailTemplatePayloadCompiler($hasher),
                $hasher,
                $store,
                new RecordingLogger(),
            ),
        );

        $subscriber->languageWritten(new EntityWrittenEvent('language', [], Context::createDefaultContext()));

        static::assertCount(1, $store->writes);
    }
}
