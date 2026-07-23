<?php declare(strict_types=1);

namespace Frosh\Jetpack\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class MappingEntity
{
    /**
     * @param class-string<EntityDefinition> $source
     * @param class-string<EntityDefinition> $target
     */
    public function __construct(
        public string $name,
        public string $source,
        public string $target,
        public string $sourceColumn,
        public string $targetColumn,
        public string $sourceReferenceField = 'id',
        public string $targetReferenceField = 'id',
        public ?string $sourceVersionColumn = null,
        public ?string $targetVersionColumn = null,
        public OnDelete $sourceOnDelete = OnDelete::Cascade,
        public OnDelete $targetOnDelete = OnDelete::Cascade,
        public ?string $renamedFrom = null,
        public string $engine = 'InnoDB',
        public string $charset = 'utf8mb4',
        public string $collation = 'utf8mb4_unicode_ci',
    ) {
    }
}
