<?php declare(strict_types=1);

namespace Frosh\Jetpack\Tests\Fixture\Entity;

use Frosh\Jetpack\Attribute\ApiScope;
use Frosh\Jetpack\Attribute\Field;
use Frosh\Jetpack\Attribute\ForeignKey;
use Frosh\Jetpack\Attribute\Id;
use Frosh\Jetpack\Attribute\ManyToMany;
use Frosh\Jetpack\Attribute\ManyToOne;
use Frosh\Jetpack\Attribute\OnDelete;
use Frosh\Jetpack\Attribute\OneToOne;
use Frosh\Jetpack\Attribute\ReferenceVersion;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

final class ExampleEntity extends Entity
{
    #[Id]
    public string $id;

    #[Field(api: ApiScope::All, translated: true)]
    public string $name;

    #[Field(api: ApiScope::Admin)]
    public ExampleMode $mode;

    #[Field(factory: TestStringFieldFactory::class)]
    public string $customValue;

    #[ForeignKey(ProductDefinition::class, onDelete: OnDelete::Cascade)]
    public string $productId;

    #[ReferenceVersion(ProductDefinition::class, forField: 'productId', column: 'product_version_id')]
    public string $productVersionId;

    #[ManyToOne(ProductDefinition::class, localField: 'productId')]
    public ?ProductEntity $product = null;

    #[OneToOne(ProductDefinition::class, localField: 'productId')]
    public ?ProductEntity $featuredProduct = null;

    #[ManyToMany(
        ProductDefinition::class,
        ExampleMappingDefinition::class,
        mappingLocalColumn: 'example_id',
        mappingReferenceColumn: 'product_id',
    )]
    public ?ProductCollection $products = null;
}
