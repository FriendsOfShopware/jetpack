<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Entity;

use Frosh\Jetpack\Attribute\Entity as EntityAttribute;
use Frosh\Jetpack\Attribute\Field as FieldAttribute;
use Frosh\Jetpack\Attribute\FieldType;
use Frosh\Jetpack\Attribute\ForeignKey;
use Frosh\Jetpack\Attribute\Id;
use Frosh\Jetpack\Attribute\ManyToOne;
use Frosh\Jetpack\Attribute\MappingEntity;
use Frosh\Jetpack\Attribute\OnDelete;
use Frosh\Jetpack\Attribute\OneToMany;
use Frosh\Jetpack\Attribute\ReferenceVersion;
use Frosh\Jetpack\Entity\BundleEntitySchemaProvider;
use Frosh\Jetpack\Entity\DalFieldCompiler;
use Frosh\Jetpack\Entity\EntitySchemaCompiler;
use Frosh\Jetpack\Entity\EntitySchemaException;
use Frosh\Jetpack\Entity\Field\BackedEnumField;
use Frosh\Jetpack\Entity\JetpackEntityDefinition;
use Frosh\Jetpack\Entity\JetpackMappingDefinition;
use Frosh\Jetpack\Entity\Schema\AssociationSchema;
use Frosh\Jetpack\Entity\Schema\AssociationType;
use Frosh\Jetpack\Entity\Schema\BundleSchema;
use Frosh\Jetpack\Entity\Schema\EntitySchema;
use Frosh\Jetpack\Tests\Fixture\Entity\ExampleDefinition;
use Frosh\Jetpack\Tests\Fixture\Entity\ExampleEntity;
use Frosh\Jetpack\Tests\Fixture\Entity\ExampleMappingDefinition;
use Frosh\Jetpack\Tests\Fixture\Entity\ExampleMode;
use Frosh\Jetpack\Tests\Fixture\Entity\ExampleTranslationDefinition;
use Frosh\Jetpack\Tests\Fixture\Entity\HierarchyDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Bundle as ShopwareBundle;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

#[CoversClass(EntitySchemaCompiler::class)]
#[CoversClass(DalFieldCompiler::class)]
final class EntitySchemaCompilerTest extends TestCase
{
    public function testCompilesConcreteDefinitionAndTypedProperties(): void
    {
        $definition = $this->definition();
        $schema = $definition->getJetpackSchema();

        static::assertSame('frosh_jetpack_example', $definition->getEntityName());
        static::assertSame(ExampleEntity::class, $definition->getEntityClass());
        static::assertSame(FieldType::Enum, $schema->field('mode')->type);
        static::assertSame(ExampleMode::class, $schema->field('mode')->enumClass);
        static::assertSame('VARCHAR(255)', $schema->field('mode')->sqlType);
        static::assertSame('VARCHAR(64)', $schema->field('customValue')->sqlType);
        static::assertFalse($schema->field('createdAt')->nullable);
        static::assertSame(['product_id', 'product_version_id'], $schema->foreignKeys[0]->localColumns);
        static::assertSame(['id', 'versionId'], $schema->foreignKeys[0]->referenceFields);
        static::assertSame(['id', 'mode', 'custom_value', 'product_id', 'product_version_id', 'version_id', 'created_at', 'updated_at'], array_map(
            static fn ($field): string => $field->storageName,
            $schema->columns(),
        ));

        $fields = (new DalFieldCompiler())->compile($schema);
        static::assertInstanceOf(TranslatedField::class, $this->field($fields, 'name'));
        static::assertInstanceOf(BackedEnumField::class, $this->field($fields, 'mode'));
        static::assertInstanceOf(StringField::class, $this->field($fields, 'customValue'));
        static::assertInstanceOf(ManyToOneAssociationField::class, $this->field($fields, 'product'));
        static::assertInstanceOf(OneToOneAssociationField::class, $this->field($fields, 'featuredProduct'));
        static::assertInstanceOf(ManyToManyAssociationField::class, $this->field($fields, 'products'));
        static::assertTrue($this->field($fields, 'products')->is(CascadeDelete::class));
        static::assertFalse($this->field($fields, 'productId')?->is(ApiAware::class));
        static::assertFalse($this->field($fields, 'product')?->is(ApiAware::class));
        $modeApi = $this->field($fields, 'mode')?->getFlag(ApiAware::class);
        static::assertInstanceOf(ApiAware::class, $modeApi);
        static::assertTrue($modeApi->isSourceAllowed(AdminApiSource::class));
        static::assertFalse($modeApi->isSourceAllowed(SalesChannelApiSource::class));
    }

    public function testLegacySchemaWithoutMaterializedSqlTypesKeepsItsSerializedShape(): void
    {
        $data = (new EntitySchemaCompiler())->compile(ExampleDefinition::class)->toArray();
        $fields = $data['fields'];
        static::assertIsArray($fields);
        foreach ($fields as &$field) {
            if (\is_array($field)) {
                unset($field['sqlType']);
            }
        }
        unset($field);
        $data['fields'] = $fields;

        static::assertSame($data, EntitySchema::fromArray($data)->toArray());
    }

    public function testCompilesTranslationTableFromParentTranslatedFields(): void
    {
        $schema = (new EntitySchemaCompiler())->compile(ExampleTranslationDefinition::class);

        static::assertSame('frosh_jetpack_example_translation', $schema->entityName);
        static::assertSame(['name', 'frosh_jetpack_example_id', 'language_id', 'frosh_jetpack_example_version_id', 'created_at', 'updated_at'], array_map(
            static fn ($field): string => $field->storageName,
            $schema->columns(),
        ));
        static::assertCount(2, $schema->foreignKeys);
        static::assertSame(
            ['frosh_jetpack_example_id', 'frosh_jetpack_example_version_id'],
            $schema->foreignKeys[0]->localColumns,
        );
    }

    public function testCompilesVersionAwareManyToManyMappingTable(): void
    {
        $schema = (new EntitySchemaCompiler())->compile(ExampleMappingDefinition::class);
        /** @phpstan-ignore method.deprecated (The supported Shopware range is 6.6 and 6.7.) */
        $definition = new ExampleMappingDefinition();

        static::assertSame('frosh_jetpack_example_product', $schema->entityName);
        static::assertTrue($definition->isVersionAware());
        static::assertCount(4, $schema->columns());
        static::assertCount(2, $schema->foreignKeys);
        static::assertSame(['example_id', 'frosh_jetpack_example_version_id'], $schema->foreignKeys[0]->localColumns);
        static::assertSame(['product_id', 'product_version_id'], $schema->foreignKeys[1]->localColumns);
    }

    public function testSnapshotRoundTripPreservesCanonicalSchema(): void
    {
        $schema = new BundleSchema('Example', [
            (new EntitySchemaCompiler())->compile(ExampleDefinition::class),
            (new EntitySchemaCompiler())->compile(ExampleTranslationDefinition::class),
            (new EntitySchemaCompiler())->compile(ExampleMappingDefinition::class),
        ]);

        $restored = BundleSchema::fromArray($schema->toArray());

        static::assertSame($schema->fingerprint(), $restored->fingerprint());
        static::assertSame($schema->toArray(), $restored->toArray());
    }

    public function testCompilesInheritanceToSpecializedShopwareParentFields(): void
    {
        /** @phpstan-ignore method.deprecated (The supported Shopware range is 6.6 and 6.7.) */
        $definition = new HierarchyDefinition();
        $schema = $definition->getJetpackSchema();
        $fields = (new DalFieldCompiler())->compile($schema);

        static::assertTrue($definition->isInheritanceAware());
        static::assertInstanceOf(ParentFkField::class, $this->field($fields, 'parentId'));
        static::assertInstanceOf(ParentAssociationField::class, $this->field($fields, 'parent'));
        static::assertInstanceOf(ChildrenAssociationField::class, $this->field($fields, 'children'));
        static::assertSame(['parent_id'], $schema->foreignKeys[0]->localColumns);
    }

    public function testCompilesGenericOneToManyAssociation(): void
    {
        $schema = $this->definition()->getJetpackSchema();
        $schema = new EntitySchema(
            definitionClass: $schema->definitionClass,
            entityClass: $schema->entityClass,
            collectionClass: $schema->collectionClass,
            entityName: $schema->entityName,
            fields: $schema->fields,
            associations: [...$schema->associations, new AssociationSchema(
                AssociationType::OneToMany,
                'relatedProducts',
                ProductDefinition::class,
                'id',
                'id',
            )],
            indexes: $schema->indexes,
            foreignKeys: $schema->foreignKeys,
            translationDefinition: $schema->translationDefinition,
        );

        $fields = (new DalFieldCompiler())->compile($schema);

        static::assertInstanceOf(OneToManyAssociationField::class, $this->field($fields, 'relatedProducts'));
    }

    public function testProviderRejectsDuplicateDefinitionRegistration(): void
    {
        $definition = $this->definition();
        $provider = new BundleEntitySchemaProvider([$definition, $definition]);

        $this->expectException(EntitySchemaException::class);
        $this->expectExceptionMessage('declared more than once');

        $provider->all();
    }

    public function testBundleProviderChecksGlobalEntityNameUniquenessBeforeFiltering(): void
    {
        $definition = $this->definition();
        $provider = new BundleEntitySchemaProvider([$definition, $definition]);

        $this->expectException(EntitySchemaException::class);
        $this->expectExceptionMessage('declared more than once');

        $provider->forBundle(new ProviderTestBundle());
    }

    public function testProviderRejectsMissingFieldOnJetpackForeignKeyTarget(): void
    {
        /** @phpstan-ignore method.deprecated (The supported Shopware range is 6.6 and 6.7.) */
        $definition = new InvalidReferenceDefinition();
        $provider = new BundleEntitySchemaProvider([
            $definition,
            $this->definition(),
        ]);

        $this->expectException(EntitySchemaException::class);
        $this->expectExceptionMessage('references missing field');

        $provider->all();
    }

    public function testRejectsAssociationWhoseReferenceFieldDiffersFromItsForeignKey(): void
    {
        $this->expectException(EntitySchemaException::class);
        $this->expectExceptionMessage('but its foreign key references');

        (new EntitySchemaCompiler())->compile(MismatchedAssociationDefinition::class);
    }

    public function testRejectsOneToManyWhoseLocalFieldDoesNotExist(): void
    {
        $this->expectException(EntitySchemaException::class);
        $this->expectExceptionMessage('missing local field');

        (new EntitySchemaCompiler())->compile(MissingLocalFieldDefinition::class);
    }

    public function testRejectsTranslatedRuntimeField(): void
    {
        $this->expectException(EntitySchemaException::class);
        $this->expectExceptionMessage('cannot be both translated and runtime');

        (new EntitySchemaCompiler())->compile(TranslatedRuntimeDefinition::class);
    }

    public function testRejectsDeclarationOnWrongDefinitionBaseClass(): void
    {
        $this->expectException(EntitySchemaException::class);
        $this->expectExceptionMessage('must extend');

        (new EntitySchemaCompiler())->compile(InvalidMappingBaseDefinition::class);
    }

    public function testRejectsInheritanceWithoutChildrenAssociation(): void
    {
        $this->expectException(EntitySchemaException::class);
        $this->expectExceptionMessage('children association');

        (new EntitySchemaCompiler())->compile(IncompleteHierarchyDefinition::class);
    }

    public function testRejectsMultipleDeclarationAttributes(): void
    {
        $this->expectException(EntitySchemaException::class);
        $this->expectExceptionMessage('exactly one');

        (new EntitySchemaCompiler())->compile(AmbiguousDefinition::class);
    }

    public function testRejectsSetNullOnMappingPrimaryKey(): void
    {
        $this->expectException(EntitySchemaException::class);
        $this->expectExceptionMessage('cannot use SET NULL');

        (new EntitySchemaCompiler())->compile(SetNullMappingDefinition::class);
    }

    public function testRejectsNonNullableReferenceVersionForSetNullForeignKey(): void
    {
        $this->expectException(EntitySchemaException::class);
        $this->expectExceptionMessage('must be nullable');

        (new EntitySchemaCompiler())->compile(InvalidSetNullReferenceDefinition::class);
    }

    public function testRejectsAggregateWithoutParentForeignKey(): void
    {
        $this->expectException(EntitySchemaException::class);
        $this->expectExceptionMessage('needs a foreign key to parent');

        (new EntitySchemaCompiler())->compile(ParentlessAggregateDefinition::class);
    }

    public function testRejectsPropertyTypeThatDalCannotHydrate(): void
    {
        $this->expectException(EntitySchemaException::class);
        $this->expectExceptionMessage('cannot hydrate PHP type DateTime');

        (new EntitySchemaCompiler())->compile(MutableDateDefinition::class);
    }

    private function definition(): ExampleDefinition
    {
        /** @phpstan-ignore method.deprecated (The supported Shopware range is 6.6 and 6.7.) */
        return new ExampleDefinition();
    }

    private function field(FieldCollection $fields, string $property): ?Field
    {
        foreach ($fields as $field) {
            if ($field->getPropertyName() === $property) {
                return $field;
            }
        }

        return null;
    }
}

final class InvalidDeclarationEntity extends Entity
{
    #[Id]
    public string $id;

    #[ForeignKey(ProductDefinition::class, referenceField: 'id')]
    public string $productId;

    #[ManyToOne(ProductDefinition::class, localField: 'productId', referenceField: 'versionId')]
    public mixed $product;
}

#[EntityAttribute(name: 'frosh_mismatched_association', entity: InvalidDeclarationEntity::class)]
final class MismatchedAssociationDefinition extends JetpackEntityDefinition
{
}

final class MissingLocalFieldEntity extends Entity
{
    #[Id]
    public string $id;

    #[OneToMany(ProductDefinition::class, referenceField: 'id', localField: 'missing')]
    public mixed $products;
}

#[EntityAttribute(name: 'frosh_missing_local_field', entity: MissingLocalFieldEntity::class)]
final class MissingLocalFieldDefinition extends JetpackEntityDefinition
{
}

final class TranslatedRuntimeEntity extends Entity
{
    #[Id]
    public string $id;

    #[FieldAttribute(translated: true, runtime: true)]
    public string $runtimeTranslation;
}

#[EntityAttribute(name: 'frosh_translated_runtime', entity: TranslatedRuntimeEntity::class)]
final class TranslatedRuntimeDefinition extends JetpackEntityDefinition
{
}

#[MappingEntity(
    name: 'frosh_invalid_mapping_base',
    source: ExampleDefinition::class,
    target: ProductDefinition::class,
    sourceColumn: 'example_id',
    targetColumn: 'product_id',
)]
final class InvalidMappingBaseDefinition extends JetpackEntityDefinition
{
}

final class IncompleteHierarchyEntity extends Entity
{
    #[Id]
    public string $id;

    #[ForeignKey(IncompleteHierarchyDefinition::class, onDelete: OnDelete::Cascade)]
    public ?string $parentId = null;

    #[ManyToOne(IncompleteHierarchyDefinition::class, localField: 'parentId')]
    public ?IncompleteHierarchyEntity $parent = null;
}

#[EntityAttribute(name: 'frosh_incomplete_hierarchy', entity: IncompleteHierarchyEntity::class, inheritanceAware: true)]
final class IncompleteHierarchyDefinition extends JetpackEntityDefinition
{
}

#[EntityAttribute(name: 'frosh_ambiguous', entity: InvalidDeclarationEntity::class)]
#[MappingEntity(
    name: 'frosh_ambiguous_mapping',
    source: ExampleDefinition::class,
    target: ProductDefinition::class,
    sourceColumn: 'example_id',
    targetColumn: 'product_id',
)]
final class AmbiguousDefinition extends JetpackEntityDefinition
{
}

final class ProviderTestBundle extends ShopwareBundle
{
    public function getPath(): string
    {
        return __DIR__ . '/not-the-definition-path';
    }
}

#[MappingEntity(
    name: 'frosh_set_null_mapping',
    source: ExampleDefinition::class,
    target: ProductDefinition::class,
    sourceColumn: 'example_id',
    targetColumn: 'product_id',
    sourceOnDelete: OnDelete::SetNull,
)]
final class SetNullMappingDefinition extends JetpackMappingDefinition
{
}

final class InvalidSetNullReferenceEntity extends Entity
{
    #[Id]
    public string $id;

    #[ForeignKey(ProductDefinition::class, onDelete: OnDelete::SetNull)]
    public ?string $productId = null;

    #[ReferenceVersion(ProductDefinition::class, forField: 'productId')]
    public string $productVersionId;
}

#[EntityAttribute(name: 'frosh_invalid_set_null_reference', entity: InvalidSetNullReferenceEntity::class)]
final class InvalidSetNullReferenceDefinition extends JetpackEntityDefinition
{
}

final class InvalidReferenceEntity extends Entity
{
    #[Id]
    public string $id;

    #[ForeignKey(ExampleDefinition::class, referenceField: 'missing')]
    public string $exampleId;

    #[ManyToOne(ExampleDefinition::class, localField: 'exampleId', referenceField: 'missing')]
    public ?ExampleEntity $example = null;
}

#[EntityAttribute(name: 'frosh_invalid_reference', entity: InvalidReferenceEntity::class)]
final class InvalidReferenceDefinition extends JetpackEntityDefinition
{
}

final class ParentlessAggregateEntity extends Entity
{
    #[Id]
    public string $id;
}

#[EntityAttribute(
    name: 'frosh_parentless_aggregate',
    entity: ParentlessAggregateEntity::class,
    parent: ExampleDefinition::class,
)]
final class ParentlessAggregateDefinition extends JetpackEntityDefinition
{
}

final class MutableDateEntity extends Entity
{
    #[Id]
    public string $id;

    #[FieldAttribute(type: FieldType::DateTime)]
    public \DateTime $scheduledAt;
}

#[EntityAttribute(name: 'frosh_mutable_date', entity: MutableDateEntity::class)]
final class MutableDateDefinition extends JetpackEntityDefinition
{
}
