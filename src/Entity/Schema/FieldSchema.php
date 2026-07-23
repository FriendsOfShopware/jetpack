<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Schema;

use Frosh\Jetpack\Attribute\ApiScope;
use Frosh\Jetpack\Attribute\FieldType;
use Frosh\Jetpack\Attribute\OnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

final readonly class FieldSchema
{
    /**
     * @param class-string<\BackedEnum>|null $enumClass
     * @param string|null $fieldFactory A factory class name; historical snapshots may reference a removed class
     * @param class-string<EntityDefinition>|null $referenceDefinition
     */
    public function __construct(
        public string $propertyName,
        public string $storageName,
        public FieldType $type,
        public bool $nullable,
        public bool $required,
        public bool $primaryKey,
        public ApiScope $api,
        public ?int $length = null,
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
        public ?string $enumClass = null,
        public ?string $fieldFactory = null,
        public ?string $sqlType = null,
        public ?string $referenceDefinition = null,
        public string $referenceField = 'id',
        public OnDelete $onDelete = OnDelete::Restrict,
        public ?string $constraintName = null,
        public bool $defaultField = false,
        public ?string $referenceVersionFor = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'property' => $this->propertyName,
            'column' => $this->storageName,
            'type' => $this->type->value,
            'nullable' => $this->nullable,
            'required' => $this->required,
            'primaryKey' => $this->primaryKey,
            'api' => $this->api->value,
            'length' => $this->length,
            'writeProtected' => $this->writeProtected,
            'runtime' => $this->runtime,
            'computed' => $this->computed,
            'inherited' => $this->inherited,
            'inheritanceForeignKey' => $this->inheritanceForeignKey,
            'searchRanking' => $this->searchRanking,
            'tokenize' => $this->tokenize,
            'allowHtml' => $this->allowHtml,
            'allowEmptyString' => $this->allowEmptyString,
            'translated' => $this->translated,
            'hasDefault' => $this->hasDefault,
            'default' => $this->default,
            'renamedFrom' => $this->renamedFrom,
            'enumClass' => $this->enumClass,
            'fieldFactory' => $this->fieldFactory,
            'referenceDefinition' => $this->referenceDefinition,
            'referenceField' => $this->referenceField,
            'onDelete' => $this->onDelete->value,
            'constraintName' => $this->constraintName,
            'defaultField' => $this->defaultField,
            'referenceVersionFor' => $this->referenceVersionFor,
        ];
        if ($this->sqlType !== null) {
            $data['sqlType'] = $this->sqlType;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            propertyName: self::string($data, 'property'),
            storageName: self::string($data, 'column'),
            type: FieldType::from(self::string($data, 'type')),
            nullable: self::bool($data, 'nullable'),
            required: self::bool($data, 'required'),
            primaryKey: self::bool($data, 'primaryKey'),
            api: ApiScope::from(self::string($data, 'api')),
            length: self::nullableInt($data, 'length'),
            writeProtected: self::bool($data, 'writeProtected'),
            runtime: self::bool($data, 'runtime'),
            computed: self::bool($data, 'computed'),
            inherited: self::bool($data, 'inherited'),
            inheritanceForeignKey: self::nullableString($data, 'inheritanceForeignKey'),
            searchRanking: self::nullableFloat($data, 'searchRanking'),
            tokenize: self::bool($data, 'tokenize'),
            allowHtml: self::bool($data, 'allowHtml'),
            allowEmptyString: self::bool($data, 'allowEmptyString'),
            translated: self::bool($data, 'translated'),
            hasDefault: self::bool($data, 'hasDefault'),
            default: self::scalar($data, 'default'),
            renamedFrom: self::nullableString($data, 'renamedFrom'),
            enumClass: self::nullableEnumClass($data, 'enumClass'),
            fieldFactory: self::nullableFieldFactory($data, 'fieldFactory'),
            sqlType: self::nullableString($data, 'sqlType'),
            referenceDefinition: self::nullableDefinitionClass($data, 'referenceDefinition'),
            referenceField: self::string($data, 'referenceField'),
            onDelete: OnDelete::from(self::string($data, 'onDelete')),
            constraintName: self::nullableString($data, 'constraintName'),
            defaultField: self::bool($data, 'defaultField'),
            referenceVersionFor: self::nullableString($data, 'referenceVersionFor'),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value)) {
            throw new \InvalidArgumentException(\sprintf('Snapshot key "%s" must be a string.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !\is_string($value)) {
            throw new \InvalidArgumentException(\sprintf('Snapshot key "%s" must be a string or null.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;
        if (!\is_bool($value)) {
            throw new \InvalidArgumentException(\sprintf('Snapshot key "%s" must be a bool.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !\is_int($value)) {
            throw new \InvalidArgumentException(\sprintf('Snapshot key "%s" must be an int or null.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function nullableFloat(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !\is_float($value) && !\is_int($value)) {
            throw new \InvalidArgumentException(\sprintf('Snapshot key "%s" must be numeric or null.', $key));
        }

        return $value === null ? null : (float) $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function scalar(array $data, string $key): string|int|float|bool|null
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !\is_scalar($value)) {
            throw new \InvalidArgumentException(\sprintf('Snapshot key "%s" must be scalar or null.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $data
     * @return class-string<\BackedEnum>|null
     */
    private static function nullableEnumClass(array $data, string $key): ?string
    {
        $value = self::nullableString($data, $key);

        /** @var class-string<\BackedEnum>|null $value Historical snapshots may reference a removed enum. */
        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function nullableFieldFactory(array $data, string $key): ?string
    {
        $value = self::nullableString($data, $key);

        return $value;
    }

    /** @param array<string, mixed> $data
     * @return class-string<EntityDefinition>|null
     */
    private static function nullableDefinitionClass(array $data, string $key): ?string
    {
        $value = self::nullableString($data, $key);

        /** @var class-string<EntityDefinition>|null $value Historical snapshots may reference a removed definition. */
        return $value;
    }
}
