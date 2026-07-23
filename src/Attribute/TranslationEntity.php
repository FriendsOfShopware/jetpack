<?php declare(strict_types=1);

namespace Frosh\Jetpack\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\Entity as DalEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Struct\ArrayEntity;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class TranslationEntity
{
    /**
     * @param class-string<EntityDefinition> $parent
     * @param class-string<DalEntity> $entity
     * @param class-string $collection
     */
    public function __construct(
        public string $parent,
        public string $entity = ArrayEntity::class,
        public string $collection = EntityCollection::class,
    ) {
    }
}
