<?php declare(strict_types=1);

namespace Frosh\Jetpack\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class ForeignKey extends Field
{
    /**
     * @param class-string<EntityDefinition> $target
     */
    public function __construct(
        public string $target,
        public string $referenceField = 'id',
        public OnDelete $onDelete = OnDelete::Restrict,
        public ?string $constraintName = null,
        ?string $column = null,
        ApiScope $api = ApiScope::None,
        ?bool $required = null,
        bool $inherited = false,
        ?string $renamedFrom = null,
    ) {
        parent::__construct(
            type: FieldType::Uuid,
            column: $column,
            api: $api,
            required: $required,
            inherited: $inherited,
            renamedFrom: $renamedFrom,
        );
    }
}
