<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\MailTemplate;

use Doctrine\DBAL\DriverManager;
use Frosh\Jetpack\MailTemplate\DbalMailTemplateStore;
use Frosh\Jetpack\MailTemplate\MailTemplateHasher;
use Frosh\Jetpack\MailTemplate\MailTemplateReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(DbalMailTemplateStore::class)]
final class DbalMailTemplateStoreTest extends TestCase
{
    public function testReadsLanguagesOwnershipAndModificationHashes(): void
    {
        if (!\extension_loaded('pdo_sqlite')) {
            static::markTestSkipped('The pdo_sqlite extension is required for this database adapter test.');
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->createTables($connection);
        $typeId = Uuid::randomHex();
        $templateId = Uuid::randomHex();
        $ownershipId = Uuid::randomHex();
        $connection->insert('locale', ['id' => Uuid::randomBytes(), 'code' => 'en-GB']);
        $localeId = $connection->fetchOne('SELECT id FROM locale');
        static::assertIsString($localeId);
        $connection->insert('language', [
            'id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'locale_id' => $localeId,
            'created_at' => '2026-07-22 12:00:00',
        ]);
        $connection->insert('mail_template_type', [
            'id' => Uuid::fromHexToBytes($typeId),
            'technical_name' => 'acme_order_confirmation',
        ]);
        $connection->insert('mail_template', [
            'id' => Uuid::fromHexToBytes($templateId),
            'mail_template_type_id' => Uuid::fromHexToBytes($typeId),
        ]);
        $connection->insert('mail_template_type_translation', [
            'mail_template_type_id' => Uuid::fromHexToBytes($typeId),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'name' => 'Order confirmation',
        ]);
        $connection->insert('mail_template_translation', [
            'mail_template_id' => Uuid::fromHexToBytes($templateId),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'subject' => 'Your order',
            'sender_name' => 'Acme',
            'description' => 'Description',
            'content_html' => '<h1>Your order</h1>',
            'content_plain' => 'Your order',
        ]);
        $connection->insert('frosh_jetpack_mail_template', [
            'id' => Uuid::fromHexToBytes($ownershipId),
            'bundle_name' => 'AcmePlugin',
            'technical_name' => 'acme_order_confirmation',
            'mail_template_type_id' => Uuid::fromHexToBytes($typeId),
            'mail_template_id' => Uuid::fromHexToBytes($templateId),
            'available_entities_hash' => hash('sha256', 'entities'),
        ]);
        $connection->insert('frosh_jetpack_mail_template_translation', [
            'mail_template_ownership_id' => Uuid::fromHexToBytes($ownershipId),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'type_name_hash' => hash('sha256', 'previous name'),
            'content_hash' => hash('sha256', 'previous content'),
        ]);
        $hasher = new MailTemplateHasher();
        $store = new DbalMailTemplateStore(
            $connection,
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            $hasher,
        );

        static::assertSame([[
            'id' => Defaults::LANGUAGE_SYSTEM,
            'locale' => 'en-GB',
            'system' => true,
        ]], $store->languages());
        $reference = $store->reference('AcmePlugin', 'acme_order_confirmation');
        static::assertEquals(new MailTemplateReference(
            'AcmePlugin',
            'acme_order_confirmation',
            $typeId,
            $templateId,
        ), $reference);
        static::assertSame($typeId, $store->typeId('acme_order_confirmation'));
        static::assertSame($typeId, $store->templateTypeId($templateId));
        static::assertNotNull($reference);
        $state = $store->state($reference);
        static::assertTrue($state['typeExists']);
        static::assertTrue($state['templateExists']);
        $translation = $state['translations'][Defaults::LANGUAGE_SYSTEM];
        static::assertSame($hasher->name('Order confirmation'), $translation['currentNameHash']);
        static::assertSame(hash('sha256', 'previous content'), $translation['synchronizedContentHash']);
        static::assertTrue($translation['managed']);
    }

    private function createTables(\Doctrine\DBAL\Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE locale (id BLOB, code TEXT)');
        $connection->executeStatement('CREATE TABLE language (id BLOB, locale_id BLOB, created_at TEXT)');
        $connection->executeStatement('CREATE TABLE mail_template_type (id BLOB, technical_name TEXT)');
        $connection->executeStatement('CREATE TABLE mail_template (id BLOB, mail_template_type_id BLOB)');
        $connection->executeStatement('CREATE TABLE mail_template_type_translation (mail_template_type_id BLOB, language_id BLOB, name TEXT)');
        $connection->executeStatement('CREATE TABLE mail_template_translation (mail_template_id BLOB, language_id BLOB, subject TEXT, sender_name TEXT, description TEXT, content_html TEXT, content_plain TEXT)');
        $connection->executeStatement('CREATE TABLE frosh_jetpack_mail_template (id BLOB, bundle_name TEXT, technical_name TEXT, mail_template_type_id BLOB, mail_template_id BLOB, available_entities_hash TEXT)');
        $connection->executeStatement('CREATE TABLE frosh_jetpack_mail_template_translation (mail_template_ownership_id BLOB, language_id BLOB, type_name_hash TEXT, content_hash TEXT)');
    }
}
