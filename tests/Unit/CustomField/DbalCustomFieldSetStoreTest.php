<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\CustomField;

use Doctrine\DBAL\DriverManager;
use Frosh\Jetpack\CustomField\DbalCustomFieldSetStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(DbalCustomFieldSetStore::class)]
final class DbalCustomFieldSetStoreTest extends TestCase
{
    public function testReadsChildrenAndReplacesIndependentOwnership(): void
    {
        if (!\extension_loaded('pdo_sqlite')) {
            static::markTestSkipped('The pdo_sqlite extension is required for this database adapter test.');
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE frosh_jetpack_custom_field_set (bundle_name TEXT, set_name TEXT, set_id BLOB)');
        $connection->executeStatement('CREATE TABLE custom_field_set (id BLOB)');
        $connection->executeStatement('CREATE TABLE custom_field_set_relation (id BLOB, set_id BLOB, entity_name TEXT)');
        $connection->executeStatement('CREATE TABLE custom_field (id BLOB, set_id BLOB, name TEXT, type TEXT)');

        $setId = Uuid::randomHex();
        $relationId = Uuid::randomHex();
        $fieldId = Uuid::randomHex();
        $connection->insert('frosh_jetpack_custom_field_set', [
            'bundle_name' => 'AcmePlugin',
            'set_name' => 'acme_details',
            'set_id' => Uuid::fromHexToBytes($setId),
        ]);
        $connection->insert('custom_field_set', ['id' => Uuid::fromHexToBytes($setId)]);
        $connection->insert('custom_field_set_relation', [
            'id' => Uuid::fromHexToBytes($relationId),
            'set_id' => Uuid::fromHexToBytes($setId),
            'entity_name' => 'product',
        ]);
        $connection->insert('custom_field', [
            'id' => Uuid::fromHexToBytes($fieldId),
            'set_id' => Uuid::fromHexToBytes($setId),
            'name' => 'acme_badge',
            'type' => 'select',
        ]);

        $store = new DbalCustomFieldSetStore(
            $connection,
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
        );

        static::assertSame(['acme_details' => $setId], $store->owned('AcmePlugin'));
        static::assertTrue($store->setExists($setId));
        static::assertFalse($store->setExists(Uuid::randomHex()));
        static::assertSame([
            'relations' => ['product' => $relationId],
            'fields' => ['acme_badge' => ['id' => $fieldId, 'type' => 'select']],
        ], $store->children($setId));

        $replacementId = Uuid::randomHex();
        $store->replaceOwnership('AcmePlugin', ['acme_replacement' => $replacementId]);

        static::assertSame(['acme_replacement' => $replacementId], $store->owned('AcmePlugin'));
    }
}
