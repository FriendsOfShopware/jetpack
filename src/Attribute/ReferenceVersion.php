<?php declare(strict_types=1);

namespace Frosh\Jetpack\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class ReferenceVersion extends Field
{
    /**
     * @param class-string<EntityDefinition> $target
     */
    public function __construct(
        public string $target,
        public ?string $forField = null,
        ?string $column = null,
        ?string $renamedFrom = null,
        ApiScope $api = ApiScope::None,
        bool $inherited = false,
    ) {
        parent::__construct(
            type: FieldType::ReferenceVersion,
            column: $column,
            api: $api,
            required: true,
            inherited: $inherited,
            renamedFrom: $renamedFrom,
        );
    }
}
