<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\Entity;

use Frosh\Jetpack\Attribute\ApiScope;
use Frosh\Jetpack\Attribute\FieldType;
use Frosh\Jetpack\Entity\EntitySchemaCompiler;
use Frosh\Jetpack\Entity\Field\FieldFactory;
use Frosh\Jetpack\Entity\Migration\DefinitionNameResolver;
use Frosh\Jetpack\Entity\Migration\MigrationPlanInverter;
use Frosh\Jetpack\Entity\Migration\MigrationStepRenderer;
use Frosh\Jetpack\Entity\Migration\MySqlSchemaRenderer;
use Frosh\Jetpack\Entity\Migration\OperationType;
use Frosh\Jetpack\Entity\Migration\SchemaDiffer;
use Frosh\Jetpack\Entity\Migration\SchemaOperation;
use Frosh\Jetpack\Entity\Schema\BundleSchema;
use Frosh\Jetpack\Entity\Schema\EntitySchema;
use Frosh\Jetpack\Entity\Schema\FieldSchema;
use Frosh\Jetpack\Tests\Fixture\Entity\ExampleDefinition;
use Frosh\Jetpack\Tests\Fixture\Entity\ExampleMappingDefinition;
use Frosh\Jetpack\Tests\Fixture\Entity\ExampleTranslationDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;

#[CoversClass(MigrationStepRenderer::class)]
#[CoversClass(MySqlSchemaRenderer::class)]
final class MigrationStepRendererTest extends TestCase
{
    public function testRendersGuardedShopwareMigrationWithCompositeForeignKeys(): void
    {
        $schema = new BundleSchema('Example', [
            (new EntitySchemaCompiler())->compile(ExampleDefinition::class),
            (new EntitySchemaCompiler())->compile(ExampleTranslationDefinition::class),
            (new EntitySchemaCompiler())->compile(ExampleMappingDefinition::class),
        ]);
        $plan = (new SchemaDiffer())->diff(BundleSchema::empty('Example'), $schema);
        $renderer = new MigrationStepRenderer(new MySqlSchemaRenderer(new DefinitionNameResolver()));

        $migration = $renderer->render(
            'Acme\\Example\\Migration',
            1784682000,
            'create entities',
            $plan,
            (new MigrationPlanInverter())->invert($plan),
            $schema,
            BundleSchema::empty('Example'),
        );

        static::assertSame('Migration1784682000CreateEntities', $migration->className);
        static::assertStringContainsString('extends Migration', $migration->content);
        static::assertStringContainsString('public function up(Connection $connection): void', $migration->content);
        static::assertStringContainsString('public function down(Connection $connection): void', $migration->content);
        static::assertStringContainsString('CREATE TABLE IF NOT EXISTS `frosh_jetpack_example`', $migration->content);
        static::assertStringContainsString('DROP TABLE IF EXISTS `frosh_jetpack_example`', $migration->content);
        static::assertStringContainsString('`custom_value` VARCHAR(64) NOT NULL', $migration->content);
        $mainTableOffset = strpos($migration->content, 'CREATE TABLE IF NOT EXISTS `frosh_jetpack_example`');
        static::assertIsInt($mainTableOffset);
        static::assertStringNotContainsString('`name` VARCHAR', substr(
            $migration->content,
            $mainTableOffset,
            500,
        ));
        static::assertStringContainsString('FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`)', $migration->content);
        static::assertStringContainsString('private function foreignKeyExists', $migration->content);
        static::assertStringNotContainsString('MigrationQueryGenerator', $migration->content);
        static::assertNotEmpty(\PhpToken::tokenize($migration->content, \TOKEN_PARSE));
    }

    public function testEscapesStringDefaultsWithoutDependingOnSqlMode(): void
    {
        $entity = new EntitySchema(
            definitionClass: ExampleDefinition::class,
            entityClass: $this->schema()->entityClass,
            collectionClass: $this->schema()->collectionClass,
            entityName: 'escaped_default',
            fields: [
                new FieldSchema('id', 'id', FieldType::Uuid, false, true, true, ApiScope::None),
                new FieldSchema('value', 'value', FieldType::String, false, true, false, ApiScope::None, hasDefault: true, default: 'back\\slash\'s'),
            ],
            associations: [],
            indexes: [],
        );
        $schema = new BundleSchema('Example', [$entity]);
        $sql = (new MySqlSchemaRenderer(new DefinitionNameResolver()))->sql(
            new SchemaOperation(OperationType::CreateTable, $entity->entityName, false, entity: $entity),
            $schema,
        );

        static::assertStringContainsString('DEFAULT \'back\\\\slash\'\'s\'', $sql);
    }

    public function testRejectsUnsafeCustomSqlType(): void
    {
        $base = $this->schema();
        $entity = new EntitySchema(
            definitionClass: ExampleDefinition::class,
            entityClass: $base->entityClass,
            collectionClass: $base->collectionClass,
            entityName: 'unsafe_custom_type',
            fields: [
                new FieldSchema('id', 'id', FieldType::Uuid, false, true, true, ApiScope::None),
                new FieldSchema('value', 'value', FieldType::Custom, false, true, false, ApiScope::None, fieldFactory: UnsafeSqlFieldFactory::class),
            ],
            associations: [],
            indexes: [],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unsafe SQL type');

        (new MySqlSchemaRenderer(new DefinitionNameResolver()))->sql(
            new SchemaOperation(OperationType::CreateTable, $entity->entityName, false, entity: $entity),
            new BundleSchema('Example', [$entity]),
        );
    }

    private function schema(): EntitySchema
    {
        return (new EntitySchemaCompiler())->compile(ExampleDefinition::class);
    }
}

final class UnsafeSqlFieldFactory implements FieldFactory
{
    public function create(FieldSchema $schema): Field
    {
        return new StringField($schema->storageName, $schema->propertyName);
    }

    public function sqlType(FieldSchema $schema): string
    {
        return 'VARCHAR(64), ADD COLUMN injected INT';
    }
}
