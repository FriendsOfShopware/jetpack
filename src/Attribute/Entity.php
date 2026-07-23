<?php declare(strict_types=1);

namespace Frosh\Jetpack\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\Entity as DalEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Entity
{
    /**
     * @param class-string<DalEntity> $entity
     * @param class-string $collection
     * @param class-string<EntityDefinition>|null $parent
     * @param class-string<EntityDefinition>|null $translationDefinition
     */
    public function __construct(
        public string $name,
        public string $entity,
        public string $collection = EntityCollection::class,
        public ?string $parent = null,
        public ?string $translationDefinition = null,
        public bool $versioned = false,
        public bool $inheritanceAware = false,
        public ?string $renamedFrom = null,
        public string $engine = 'InnoDB',
        public string $charset = 'utf8mb4',
        public string $collation = 'utf8mb4_unicode_ci',
    ) {
    }
}
