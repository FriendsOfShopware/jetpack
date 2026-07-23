<?php declare(strict_types=1);

namespace Frosh\Jetpack\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class ManyToOne
{
    /**
     * @param class-string<EntityDefinition> $target
     */
    public function __construct(
        public string $target,
        public string $localField,
        public string $referenceField = 'id',
        public bool $autoload = false,
        public ApiScope $api = ApiScope::None,
        public bool $inherited = false,
        public ?string $inheritanceForeignKey = null,
    ) {
    }
}
