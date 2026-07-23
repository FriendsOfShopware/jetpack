<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Entity;

use Frosh\Jetpack\Attribute\ApiScope;
use Frosh\Jetpack\Attribute\FieldType;
use Frosh\Jetpack\Entity\EntitySchemaCompiler;
use Frosh\Jetpack\Entity\Migration\OperationType;
use Frosh\Jetpack\Entity\Migration\SchemaDiffer;
use Frosh\Jetpack\Entity\Migration\UnsafeSchemaChangeException;
use Frosh\Jetpack\Entity\Schema\BundleSchema;
use Frosh\Jetpack\Entity\Schema\EntitySchema;
use Frosh\Jetpack\Entity\Schema\FieldSchema;
use Frosh\Jetpack\Tests\Fixture\Entity\ExampleDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaDiffer::class)]
final class SchemaDifferTest extends TestCase
{
    public function testPlansCompleteInitialSchemaWithoutDatabaseState(): void
    {
        $current = new BundleSchema('Example', [(new EntitySchemaCompiler())->compile(ExampleDefinition::class)]);

        $plan = (new SchemaDiffer())->diff(BundleSchema::empty('Example'), $current);

        static::assertSame(
            ['create table frosh_jetpack_example', 'add index uniq.frosh_jetpack_example.product_id on frosh_jetpack_example', 'add foreign key fk.frosh_jetpack_example.product_id on frosh_jetpack_example'],
            array_map(static fn ($operation): string => $operation->description(), $plan->safe()),
        );
        static::assertSame([], $plan->destructive());
    }

    public function testRejectsAddingRequiredColumnWithoutDatabaseDefault(): void
    {
        $old = (new EntitySchemaCompiler())->compile(ExampleDefinition::class);
        $new = new EntitySchema(
            definitionClass: $old->definitionClass,
            entityClass: $old->entityClass,
            collectionClass: $old->collectionClass,
            entityName: $old->entityName,
            fields: [...$old->fields, new FieldSchema('requiredValue', 'required_value', FieldType::String, false, true, false, ApiScope::None)],
            associations: $old->associations,
            indexes: $old->indexes,
            foreignKeys: $old->foreignKeys,
            translationDefinition: $old->translationDefinition,
        );

        $this->expectExceptionObject(UnsafeSchemaChangeException::requiredColumnWithoutDefault('frosh_jetpack_example', 'required_value'));

        (new SchemaDiffer())->diff(new BundleSchema('Example', [$old]), new BundleSchema('Example', [$new]));
    }

    public function testPlansPrimaryKeyChangesExplicitly(): void
    {
        $old = (new EntitySchemaCompiler())->compile(ExampleDefinition::class);
        $fields = array_map(
            static fn (FieldSchema $field): FieldSchema => $field->propertyName === 'id'
                ? FieldSchema::fromArray([...$field->toArray(), 'primaryKey' => false])
                : $field,
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

        $plan = (new SchemaDiffer())->diff(new BundleSchema('Example', [$old]), new BundleSchema('Example', [$new]));

        static::assertSame(
            [OperationType::DropPrimaryKey, OperationType::AddPrimaryKey],
            array_map(static fn ($operation): OperationType => $operation->type, $plan->destructive()),
        );
    }

    public function testRenameMetadataIsIdempotentAfterItWasSnapshotted(): void
    {
        $schema = (new EntitySchemaCompiler())->compile(ExampleDefinition::class);
        $renamedField = FieldSchema::fromArray([
            ...$schema->field('mode')->toArray(),
            'column' => 'state',
            'renamedFrom' => 'mode',
        ]);
        $fields = array_map(
            static fn (FieldSchema $field): FieldSchema => $field->propertyName === 'mode' ? $renamedField : $field,
            $schema->fields,
        );
        $renamed = new EntitySchema(
            definitionClass: $schema->definitionClass,
            entityClass: $schema->entityClass,
            collectionClass: $schema->collectionClass,
            entityName: 'frosh_jetpack_renamed_example',
            fields: $fields,
            associations: $schema->associations,
            indexes: [],
            foreignKeys: $schema->foreignKeys,
            translationDefinition: $schema->translationDefinition,
            renamedFrom: $schema->entityName,
        );

        $plan = (new SchemaDiffer())->diff(new BundleSchema('Example', [$renamed]), new BundleSchema('Example', [$renamed]));

        static::assertTrue($plan->isEmpty());
    }

    public function testCanDropAnEntityWhosePhpClassesNoLongerExist(): void
    {
        $schema = (new EntitySchemaCompiler())->compile(ExampleDefinition::class);
        $data = $schema->toArray();
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
        $data['fields'] = $fields;
        $historical = EntitySchema::fromArray($data);

        $plan = (new SchemaDiffer())->diff(new BundleSchema('Example', [$historical]), BundleSchema::empty('Example'));

        static::assertContains(OperationType::DropTable, array_map(static fn ($operation): OperationType => $operation->type, $plan->destructive()));
    }

    public function testTreatsTableOptionChangesAsDestructiveDdl(): void
    {
        $old = (new EntitySchemaCompiler())->compile(ExampleDefinition::class);
        $new = new EntitySchema(
            definitionClass: $old->definitionClass,
            entityClass: $old->entityClass,
            collectionClass: $old->collectionClass,
            entityName: $old->entityName,
            fields: $old->fields,
            associations: $old->associations,
            indexes: $old->indexes,
            foreignKeys: $old->foreignKeys,
            translationDefinition: $old->translationDefinition,
            engine: 'MyISAM',
        );

        $plan = (new SchemaDiffer())->diff(new BundleSchema('Example', [$old]), new BundleSchema('Example', [$new]));

        static::assertSame([OperationType::AlterTableOptions], array_map(static fn ($operation): OperationType => $operation->type, $plan->destructive()));
    }

    public function testReportsRuntimeOnlyDeclarationDriftWithoutDdl(): void
    {
        $old = (new EntitySchemaCompiler())->compile(ExampleDefinition::class);
        $new = new EntitySchema(
            definitionClass: $old->definitionClass,
            entityClass: $old->entityClass,
            collectionClass: $old->collectionClass,
            entityName: $old->entityName,
            fields: $old->fields,
            associations: $old->associations,
            indexes: $old->indexes,
            foreignKeys: $old->foreignKeys,
            translationDefinition: $old->translationDefinition,
            inheritanceAware: !$old->inheritanceAware,
        );

        $plan = (new SchemaDiffer())->diff(new BundleSchema('Example', [$old]), new BundleSchema('Example', [$new]));

        static::assertTrue($plan->isEmpty());
        static::assertTrue($plan->hasDeclarationChanges());
    }

    public function testDefersNewForeignKeysWhenThePlanHasDestructiveDependencies(): void
    {
        $current = (new EntitySchemaCompiler())->compile(ExampleDefinition::class);
        $old = new EntitySchema(
            definitionClass: $current->definitionClass,
            entityClass: $current->entityClass,
            collectionClass: $current->collectionClass,
            entityName: $current->entityName,
            fields: $current->fields,
            associations: $current->associations,
            indexes: $current->indexes,
            foreignKeys: [],
            translationDefinition: $current->translationDefinition,
            engine: 'MyISAM',
        );

        $plan = (new SchemaDiffer())->diff(new BundleSchema('Example', [$old]), new BundleSchema('Example', [$current]));

        static::assertContains(OperationType::AlterTableOptions, array_map(static fn ($operation): OperationType => $operation->type, $plan->destructive()));
        static::assertContains(OperationType::AddForeignKey, array_map(static fn ($operation): OperationType => $operation->type, $plan->destructive()));
        static::assertNotContains(OperationType::AddForeignKey, array_map(static fn ($operation): OperationType => $operation->type, $plan->safe()));
    }

    public function testRebuildsForeignKeyAroundLocalColumnAlteration(): void
    {
        $current = (new EntitySchemaCompiler())->compile(ExampleDefinition::class);
        $fields = array_map(
            static fn (FieldSchema $field): FieldSchema => $field->propertyName === 'productId'
                ? FieldSchema::fromArray([...$field->toArray(), 'nullable' => true, 'required' => false])
                : $field,
            $current->fields,
        );
        $old = new EntitySchema(
            definitionClass: $current->definitionClass,
            entityClass: $current->entityClass,
            collectionClass: $current->collectionClass,
            entityName: $current->entityName,
            fields: $fields,
            associations: $current->associations,
            indexes: $current->indexes,
            foreignKeys: $current->foreignKeys,
            translationDefinition: $current->translationDefinition,
        );

        $plan = (new SchemaDiffer())->diff(new BundleSchema('Example', [$old]), new BundleSchema('Example', [$current]));

        static::assertSame(
            [OperationType::DropForeignKey, OperationType::AlterColumn, OperationType::AddForeignKey],
            array_map(static fn ($operation): OperationType => $operation->type, $plan->destructive()),
        );
    }
}
