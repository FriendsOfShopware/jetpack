<?php declare(strict_types=1);

namespace Frosh\Jetpack\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class ManyToMany
{
    /**
     * @param class-string<EntityDefinition> $target
     * @param class-string<EntityDefinition> $mappingDefinition
     */
    public function __construct(
        public string $target,
        public string $mappingDefinition,
        public string $mappingLocalColumn,
        public string $mappingReferenceColumn,
        public string $sourceColumn = 'id',
        public string $referenceField = 'id',
        public ApiScope $api = ApiScope::None,
        public bool $inherited = false,
        public ?string $inheritanceForeignKey = null,
        public OnDelete $onDelete = OnDelete::Cascade,
    ) {
    }
}
