<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Unit\CustomField;

use Frosh\Jetpack\CustomField\CustomFieldException;
use Frosh\Jetpack\CustomField\YamlCustomFieldLoader;
use Frosh\Jetpack\Tests\Fixture\CustomFieldTestBundle;
use Frosh\Jetpack\Tests\Fixture\InvalidCustomFieldSchemaBundle;
use Frosh\Jetpack\Tests\Fixture\InvalidCustomFieldTestBundle;
use Frosh\Jetpack\Tests\Fixture\MigrationTestBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlCustomFieldLoader::class)]
#[CoversClass(CustomFieldException::class)]
final class YamlCustomFieldLoaderTest extends TestCase
{
    public function testLoadsAndNormalizesCustomFieldYaml(): void
    {
        $definition = (new YamlCustomFieldLoader())->load(new CustomFieldTestBundle());

        static::assertCount(1, $definition->sets);
        $set = $definition->sets[0];
        static::assertSame('acme_product_details', $set['name']);
        static::assertSame(['product'], $set['relations']);
        static::assertTrue($set['active']);
        static::assertFalse($set['global']);
        static::assertCount(3, $set['fields']);
        static::assertSame(1, $set['fields'][0]['position']);
        static::assertFalse($set['fields'][0]['allowCustomerWrite']);
        static::assertTrue($set['fields'][1]['includeInSearch']);
    }

    public function testReturnsEmptyDefinitionWhenYamlDoesNotExist(): void
    {
        $definition = (new YamlCustomFieldLoader())->load(new MigrationTestBundle());

        static::assertSame([], $definition->sets);
        static::assertStringEndsWith('/Resources/config/custom-fields.yaml', $definition->path);
    }

    public function testRejectsFieldNamesDuplicatedAcrossSets(): void
    {
        $this->expectExceptionObject(CustomFieldException::invalidDefinition(
            (new InvalidCustomFieldTestBundle())->getPath() . YamlCustomFieldLoader::RELATIVE_PATH,
            'Custom field name "acme_duplicate" is duplicated across sets.',
        ));

        (new YamlCustomFieldLoader())->load(new InvalidCustomFieldTestBundle());
    }

    public function testRejectsDocumentsThatDoNotMatchTheJsonSchema(): void
    {
        $this->expectException(CustomFieldException::class);
        $this->expectExceptionMessage('Invalid Jetpack custom field definition');
        $this->expectExceptionMessage('options');

        (new YamlCustomFieldLoader())->load(new InvalidCustomFieldSchemaBundle());
    }
}
