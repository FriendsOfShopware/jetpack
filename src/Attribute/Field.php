<?php declare(strict_types=1);

namespace Frosh\Jetpack\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
readonly class Field
{
    /**
     * @param class-string<\BackedEnum>|null $enum
     * @param string|null $factory Must name a zero-constructor \Frosh\Jetpack\Entity\Field\FieldFactory
     */
    public function __construct(
        public ?FieldType $type = null,
        public ?string $column = null,
        public ?int $length = null,
        public ApiScope $api = ApiScope::None,
        public ?bool $required = null,
        public bool $primaryKey = false,
        public bool $writeProtected = false,
        public bool $runtime = false,
        public bool $computed = false,
        public bool $inherited = false,
        public ?string $inheritanceForeignKey = null,
        public ?float $searchRanking = null,
        public bool $tokenize = true,
        public bool $allowHtml = false,
        public bool $allowEmptyString = false,
        public bool $translated = false,
        public bool $hasDefault = false,
        public string|int|float|bool|null $default = null,
        public ?string $renamedFrom = null,
        public ?string $enum = null,
        public ?string $factory = null,
    ) {
    }
}
