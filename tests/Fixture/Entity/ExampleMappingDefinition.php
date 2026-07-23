<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture\Entity;

use Frosh\Jetpack\Attribute\MappingEntity;
use Frosh\Jetpack\Entity\JetpackMappingDefinition;
use Shopware\Core\Content\Product\ProductDefinition;

#[MappingEntity(
    name: 'frosh_jetpack_example_product',
    source: ExampleDefinition::class,
    target: ProductDefinition::class,
    sourceColumn: 'example_id',
    targetColumn: 'product_id',
    sourceVersionColumn: 'frosh_jetpack_example_version_id',
    targetVersionColumn: 'product_version_id',
)]
final class ExampleMappingDefinition extends JetpackMappingDefinition
{
}
