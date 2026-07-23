<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\MailTemplate;

use Frosh\Jetpack\MailTemplate\MailTemplateException;
use Frosh\Jetpack\MailTemplate\MailTemplateHasher;
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
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(MailTemplateSynchronizer::class)]
final class MailTemplateSynchronizerTest extends TestCase
{
    private const ENGLISH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const GERMAN = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testCreatesTranslationsForInstalledLocales(): void
    {
        $store = $this->store();
        $synchronizer = $this->synchronizer($store);

        $synchronizer->sync(new MailTemplateTestBundle(), Context::createDefaultContext());

        static::assertCount(1, $store->writes);
        $write = $store->writes[0];
        static::assertSame('acme_order_confirmation', $write['reference']->technicalName);
        static::assertSame([
            'order' => 'order',
            'salesChannel' => 'sales_channel',
            'editOrderUrl' => null,
        ], $write['availableEntities']);
        static::assertCount(2, $write['translations']);
        static::assertSame([self::ENGLISH, self::GERMAN], array_column($write['translations'], 'languageId'));
    }

    public function testUsesDefaultLocaleForAnUntranslatedSystemLanguage(): void
    {
        $store = new InMemoryMailTemplateStore();
        $store->languageRows = [[
            'id' => self::ENGLISH,
            'locale' => 'fr-FR',
            'system' => true,
        ]];

        $this->synchronizer($store)->sync(new MailTemplateTestBundle(), Context::createDefaultContext());

        static::assertSame('Your order {{ order.orderNumber }}', $store->writes[0]['translations'][0]['subject']);
    }

    public function testPreservesMerchantContentAndLogsTheConflict(): void
    {
        $store = $this->store();
        $hasher = new MailTemplateHasher();
        $loader = $this->loader();
        $translation = $loader->load(new MailTemplateTestBundle())->templates[0]['translations']['en-GB'];
        $reference = (new MailTemplatePayloadCompiler($hasher))->reference('MailTemplateTestBundle', 'acme_order_confirmation');
        $desiredNameHash = $hasher->name($translation['name']);
        $desiredContentHash = $hasher->content(
            $translation['subject'],
            $translation['senderName'],
            $translation['description'],
            $translation['contentHtml'],
            $translation['contentPlain'],
        );
        $store->typeIds[$reference->technicalName] = $reference->templateTypeId;
        $store->templateTypeIds[$reference->templateId] = $reference->templateTypeId;
        $store->states[$reference->technicalName] = [
            'typeExists' => true,
            'templateExists' => true,
            'translations' => [
                self::ENGLISH => [
                    'currentNameHash' => $desiredNameHash,
                    'currentContentHash' => hash('sha256', 'merchant change'),
                    'synchronizedNameHash' => $desiredNameHash,
                    'synchronizedContentHash' => $desiredContentHash,
                    'managed' => true,
                ],
            ],
        ];
        $logger = new RecordingLogger();

        $this->synchronizer($store, $logger)->sync(new MailTemplateTestBundle(), Context::createDefaultContext());

        static::assertNull($store->writes[0]['translations'][0]['subject']);
        static::assertCount(1, $logger->records);
        static::assertSame('acme_order_confirmation', $logger->records[0]['context']['technicalName']);
    }

    public function testRejectsAnUnownedTechnicalNameCollisionBeforeWriting(): void
    {
        $store = $this->store();
        $store->typeIds['acme_order_confirmation'] = 'cccccccccccccccccccccccccccccccc';
        $synchronizer = $this->synchronizer($store);

        $this->expectException(MailTemplateException::class);
        $this->expectExceptionMessage('already exists with unowned ID');

        $synchronizer->sync(new MailTemplateTestBundle(), Context::createDefaultContext());
    }

    public function testRemovesDeclarationsNoLongerOwnedByTheManifest(): void
    {
        $store = $this->store();
        $obsolete = (new MailTemplatePayloadCompiler(new MailTemplateHasher()))->reference(
            'MailTemplateTestBundle',
            'acme_obsolete',
        );
        $store->ownership['MailTemplateTestBundle']['acme_obsolete'] = $obsolete;

        $this->synchronizer($store)->sync(new MailTemplateTestBundle(), Context::createDefaultContext());

        static::assertSame([$obsolete], $store->removals);
    }

    private function store(): InMemoryMailTemplateStore
    {
        $store = new InMemoryMailTemplateStore();
        $store->languageRows = [
            ['id' => self::ENGLISH, 'locale' => 'en-GB', 'system' => true],
            ['id' => self::GERMAN, 'locale' => 'de-DE', 'system' => false],
        ];

        return $store;
    }

    private function synchronizer(
        InMemoryMailTemplateStore $store,
        ?RecordingLogger $logger = null,
    ): MailTemplateSynchronizer {
        $hasher = new MailTemplateHasher();

        return new MailTemplateSynchronizer(
            $this->loader(),
            new MailTemplatePayloadCompiler($hasher),
            $hasher,
            $store,
            $logger ?? new RecordingLogger(),
        );
    }

    private function loader(): YamlMailTemplateLoader
    {
        $definitions = static::createStub(DefinitionInstanceRegistry::class);
        $definitions->method('has')->willReturnCallback(
            static fn (string $entityName): bool => \in_array($entityName, ['order', 'sales_channel'], true),
        );

        return new YamlMailTemplateLoader(new Environment(new ArrayLoader()), $definitions);
    }
}
