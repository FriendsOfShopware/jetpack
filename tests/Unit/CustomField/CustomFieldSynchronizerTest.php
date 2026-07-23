<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\CustomField;

use Frosh\Jetpack\CustomField\CustomFieldPayloadCompiler;
use Frosh\Jetpack\CustomField\CustomFieldSynchronizer;
use Frosh\Jetpack\CustomField\YamlCustomFieldLoader;
use Frosh\Jetpack\Tests\Fixture\CustomFieldTestBundle;
use Frosh\Jetpack\Tests\Fixture\InMemoryCustomFieldSetStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(CustomFieldSynchronizer::class)]
final class CustomFieldSynchronizerTest extends TestCase
{
    public function testSynchronizesUpdatesAndRemovesOnlyOwnedSets(): void
    {
        $keptSetId = Uuid::randomHex();
        $removedSetId = Uuid::randomHex();
        $obsoleteRelationId = Uuid::randomHex();
        $obsoleteFieldId = Uuid::randomHex();
        $store = new InMemoryCustomFieldSetStore();
        $bundle = new CustomFieldTestBundle();
        $store->ownership[$bundle->getName()] = [
            'acme_product_details' => $keptSetId,
            'acme_removed' => $removedSetId,
        ];
        $store->childrenBySet[$keptSetId] = [
            'relations' => ['category' => $obsoleteRelationId],
            'fields' => ['acme_old' => ['id' => $obsoleteFieldId, 'type' => 'text']],
        ];

        $synchronizer = new CustomFieldSynchronizer(
            new YamlCustomFieldLoader(),
            new CustomFieldPayloadCompiler(),
            $store,
        );
        $synchronizer->sync($bundle, Context::createDefaultContext());

        static::assertSame([$obsoleteRelationId], $store->deletedRelations);
        static::assertSame([$obsoleteFieldId], $store->deletedFields);
        static::assertSame([$removedSetId], $store->deletedSets);
        static::assertCount(1, $store->upserts);
        static::assertSame($keptSetId, $store->upserts[0]['id']);
        static::assertSame(['acme_product_details' => $keptSetId], $store->ownership[$bundle->getName()]);

        $synchronizer->remove($bundle, Context::createDefaultContext());

        static::assertSame([$removedSetId, $keptSetId], $store->deletedSets);
        static::assertSame([], $store->ownership[$bundle->getName()]);
    }

    public function testAdoptsDeterministicSetWhenOwnershipTableWasRecreated(): void
    {
        $store = new InMemoryCustomFieldSetStore();
        $bundle = new CustomFieldTestBundle();
        $compiler = new CustomFieldPayloadCompiler();
        $setId = $compiler->setId($bundle->getName(), 'acme_product_details');
        $obsoleteFieldId = Uuid::randomHex();
        $store->existingSetIds[$setId] = true;
        $store->childrenBySet[$setId] = [
            'relations' => [],
            'fields' => ['acme_old' => ['id' => $obsoleteFieldId, 'type' => 'text']],
        ];

        (new CustomFieldSynchronizer(new YamlCustomFieldLoader(), $compiler, $store))->sync(
            $bundle,
            Context::createDefaultContext(),
        );

        static::assertSame($setId, $store->upserts[0]['id']);
        static::assertArrayNotHasKey('name', $store->upserts[0]);
        static::assertSame([$obsoleteFieldId], $store->deletedFields);
        static::assertSame(['acme_product_details' => $setId], $store->ownership[$bundle->getName()]);
    }
}
