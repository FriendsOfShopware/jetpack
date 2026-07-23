<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\CustomField;

use Frosh\Jetpack\CustomField\CustomFieldException;
use Frosh\Jetpack\CustomField\CustomFieldPayloadCompiler;
use Frosh\Jetpack\CustomField\YamlCustomFieldLoader;
use Frosh\Jetpack\Tests\Fixture\CustomFieldTestBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(CustomFieldPayloadCompiler::class)]
final class CustomFieldPayloadCompilerTest extends TestCase
{
    public function testCompilesStableShopwarePayloadsWithoutNewMinorFields(): void
    {
        $set = (new YamlCustomFieldLoader())->load(new CustomFieldTestBundle())->sets[0];

        $compiled = (new CustomFieldPayloadCompiler())->compile(
            'AcmePlugin',
            $set,
            null,
            [],
            [],
            false,
        );

        static::assertTrue(Uuid::isValid($compiled['setId']));
        static::assertSame('acme_product_details', $compiled['payload']['name']);
        static::assertArrayNotHasKey('extensionName', $compiled['payload']);
        static::assertSame('select', $compiled['payload']['customFields'][0]['type']);
        static::assertSame([
            ['value' => 'new', 'label' => ['en-GB' => 'New']],
            ['value' => 'sale', 'label' => ['en-GB' => 'Sale']],
        ], $compiled['payload']['customFields'][0]['config']['options']);
        static::assertArrayNotHasKey('includeInSearch', $compiled['payload']['customFields'][1]);
        static::assertSame('product_manufacturer', $compiled['payload']['customFields'][2]['config']['entity']);
    }

    public function testPreservesImmutableIdsAndReturnsObsoleteChildren(): void
    {
        $set = (new YamlCustomFieldLoader())->load(new CustomFieldTestBundle())->sets[0];
        $setId = Uuid::randomHex();
        $relationId = Uuid::randomHex();
        $fieldId = Uuid::randomHex();
        $obsoleteRelationId = Uuid::randomHex();
        $obsoleteFieldId = Uuid::randomHex();

        $compiled = (new CustomFieldPayloadCompiler())->compile(
            'AcmePlugin',
            $set,
            $setId,
            ['product' => $relationId, 'category' => $obsoleteRelationId],
            [
                'acme_badge' => ['id' => $fieldId, 'type' => 'select'],
                'acme_old' => ['id' => $obsoleteFieldId, 'type' => 'text'],
            ],
            true,
        );

        static::assertSame($setId, $compiled['payload']['id']);
        static::assertArrayNotHasKey('name', $compiled['payload']);
        static::assertSame($relationId, $compiled['payload']['relations'][0]['id']);
        static::assertSame($fieldId, $compiled['payload']['customFields'][0]['id']);
        static::assertArrayNotHasKey('name', $compiled['payload']['customFields'][0]);
        static::assertArrayNotHasKey('type', $compiled['payload']['customFields'][0]);
        static::assertTrue($compiled['payload']['customFields'][1]['includeInSearch']);
        static::assertSame([$obsoleteRelationId], $compiled['obsoleteRelations']);
        static::assertSame([$obsoleteFieldId], $compiled['obsoleteFields']);
    }

    public function testRejectsChangingAnImmutableStorageType(): void
    {
        $set = (new YamlCustomFieldLoader())->load(new CustomFieldTestBundle())->sets[0];

        $this->expectExceptionObject(CustomFieldException::immutableFieldType('acme_badge', 'text', 'select'));

        (new CustomFieldPayloadCompiler())->compile(
            'AcmePlugin',
            $set,
            Uuid::randomHex(),
            [],
            ['acme_badge' => ['id' => Uuid::randomHex(), 'type' => 'text']],
            true,
        );
    }
}
