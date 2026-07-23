<?php declare(strict_types=1);

namespace Frosh\Jetpack\CustomField;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @phpstan-type TranslatedString array<string, string>
 * @phpstan-type SelectOption array{label: TranslatedString}
 * @phpstan-type FieldDefinition array{
 *     name: string,
 *     type: string,
 *     label: TranslatedString,
 *     helpText: TranslatedString,
 *     placeholder: TranslatedString,
 *     position: int,
 *     active: bool,
 *     required: bool,
 *     allowCustomerWrite: bool,
 *     allowCartExpose: bool,
 *     includeInSearch: bool,
 *     min?: int|float,
 *     max?: int|float,
 *     step?: int|float,
 *     options?: array<string, SelectOption>,
 *     entity?: string,
 *     labelProperty?: string
 * }
 * @phpstan-type SetDefinition array{
 *     name: string,
 *     label: TranslatedString,
 *     global: bool,
 *     active: bool,
 *     position: int,
 *     relations: list<string>,
 *     fields: list<FieldDefinition>
 * }
 */
final readonly class CustomFieldDefinition
{
    /**
     * @param list<SetDefinition> $sets
     */
    public function __construct(
        public string $path,
        public array $sets,
    ) {
    }
}
