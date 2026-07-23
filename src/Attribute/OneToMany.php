<?php declare(strict_types=1);

namespace Frosh\Jetpack\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class OneToMany
{
    /**
     * @param class-string<EntityDefinition> $target
     */
    public function __construct(
        public string $target,
        public string $referenceField,
        public string $localField = 'id',
        public ApiScope $api = ApiScope::None,
        public bool $inherited = false,
        public ?string $inheritanceForeignKey = null,
        public ?OnDelete $onDelete = null,
    ) {
    }
}
