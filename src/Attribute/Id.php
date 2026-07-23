<?php declare(strict_types=1);

namespace Frosh\Jetpack\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Id extends Field
{
    public function __construct(
        ?string $column = null,
        ApiScope $api = ApiScope::All,
        ?string $renamedFrom = null,
    ) {
        parent::__construct(
            type: FieldType::Uuid,
            column: $column,
            api: $api,
            required: true,
            primaryKey: true,
            renamedFrom: $renamedFrom,
        );
    }
}
