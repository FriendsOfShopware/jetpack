<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Entity;

use Frosh\Jetpack\Attribute\ApiScope;
use Frosh\Jetpack\Attribute\FieldType;
use Frosh\Jetpack\Entity\EntitySchemaCompiler;
use Frosh\Jetpack\Entity\Migration\DefinitionNameResolver;
use Frosh\Jetpack\Entity\Migration\MigrationPlanInverter;
use Frosh\Jetpack\Entity\Migration\MigrationStepRenderer;
use Frosh\Jetpack\Entity\Migration\MySqlSchemaRenderer;
use Frosh\Jetpack\Entity\Migration\OperationType;
use Frosh\Jetpack\Entity\Migration\SchemaDiffer;
use Frosh\Jetpack\Entity\Schema\BundleSchema;
use Frosh\Jetpack\Entity\Schema\EntitySchema;
use Frosh\Jetpack\Entity\Schema\FieldSchema;
use Frosh\Jetpack\Tests\Fixture\Entity\ExampleDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MigrationPlanInverter::class)]
final class MigrationPlanInverterTest extends TestCase
{
    public function testInitialSchemaIsRemovedInDependencySafeOrder(): void
    {
        $current = new BundleSchema('Example', [(new EntitySchemaCompiler())->compile(ExampleDefinition::class)]);
        $up = (new SchemaDiffer())->diff(BundleSchema::empty('Example'), $current);

        $down = (new MigrationPlanInverter())->invert($up);

        static::assertSame(
            [
                'drop foreign key fk.frosh_jetpack_example.product_id on frosh_jetpack_example',
                'drop index uniq.frosh_jetpack_example.product_id on frosh_jetpack_example',
                'drop table frosh_jetpack_example',
            ],
            array_map(static fn ($operation): string => $operation->description(), $down->operations),
        );
    }

    public function testDroppedTableRestoresItsIndexesAndForeignKeys(): void
    {
        $previous = new BundleSchema('Example', [(new EntitySchemaCompiler())->compile(ExampleDefinition::class)]);
        $up = (new SchemaDiffer())->diff($previous, BundleSchema::empty('Example'));

        $down = (new MigrationPlanInverter())->invert($up);

        static::assertSame(
            [OperationType::CreateTable, OperationType::AddIndex, OperationType::AddForeignKey],
            array_map(static fn ($operation): OperationType => $operation->type, $down->operations),
        );
    }

    public function testRenamedAndAlteredColumnUsesCurrentNameUntilItIsRenamedBack(): void
    {
        $old = (new EntitySchemaCompiler())->compile(ExampleDefinition::class);
        $newField = new FieldSchema(
            propertyName: 'mode',
            storageName: 'state',
            type: FieldType::String,
            nullable: true,
            required: false,
            primaryKey: false,
            api: ApiScope::None,
            length: 64,
            renamedFrom: 'mode',
        );
        $fields = array_map(
            static fn (FieldSchema $field): FieldSchema => $field->propertyName === 'mode' ? $newField : $field,
            $old->fields,
        );
        $new = new EntitySchema(
            definitionClass: $old->definitionClass,
            entityClass: $old->entityClass,
            collectionClass: $old->collectionClass,
            entityName: $old->entityName,
            fields: $fields,
            associations: $old->associations,
            indexes: $old->indexes,
            foreignKeys: $old->foreignKeys,
            translationDefinition: $old->translationDefinition,
        );
        $up = (new SchemaDiffer())->diff(new BundleSchema('Example', [$old]), new BundleSchema('Example', [$new]));

        $down = (new MigrationPlanInverter())->invert($up);

        static::assertSame([OperationType::AlterColumn, OperationType::RenameColumn], array_map(
            static fn ($operation): OperationType => $operation->type,
            $down->operations,
        ));
        static::assertSame('state', $down->operations[0]->field?->storageName);
        static::assertSame('mode', $down->operations[1]->field?->storageName);
    }

    public function testRollbackCanRenderRemovedCustomFieldFromMaterializedSnapshotType(): void
    {
        $data = (new EntitySchemaCompiler())->compile(ExampleDefinition::class)->toArray();
        $data['definitionClass'] = 'Deleted\\ExampleDefinition';
        $data['entityClass'] = 'Deleted\\ExampleEntity';
        $data['collectionClass'] = 'Deleted\\ExampleCollection';
        $fields = $data['fields'];
        static::assertIsArray($fields);
        foreach ($fields as &$field) {
            if (\is_array($field) && ($field['property'] ?? null) === 'customValue') {
                $field['fieldFactory'] = 'Deleted\\CustomFieldFactory';
            }
        }
        unset($field);
        $historical = EntitySchema::fromArray($data);
        $previous = new BundleSchema('Example', [$historical]);
        $current = BundleSchema::empty('Example');
        $up = (new SchemaDiffer())->diff($previous, $current);
        $down = (new MigrationPlanInverter())->invert($up);

        $migration = (new MigrationStepRenderer(new MySqlSchemaRenderer(new DefinitionNameResolver())))->render(
            'Acme\\Example\\Migration',
            1785000000,
            'drop example',
            $up,
            $down,
            $current,
            $previous,
        );

        static::assertStringContainsString('`custom_value` VARCHAR(64) NOT NULL', $migration->content);
        static::assertNotEmpty(\PhpToken::tokenize($migration->content, \TOKEN_PARSE));
    }
}
