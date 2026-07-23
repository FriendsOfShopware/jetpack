<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Schema;

use Frosh\Jetpack\Attribute\ApiScope;
use Frosh\Jetpack\Attribute\OnDelete;

final readonly class AssociationSchema
{
    /**
     * @param class-string $targetDefinition
     * @param class-string|null $mappingDefinition
     */
    public function __construct(
        public AssociationType $type,
        public string $propertyName,
        public string $targetDefinition,
        public string $localField,
        public string $referenceField = 'id',
        public bool $autoload = false,
        public ApiScope $api = ApiScope::None,
        public bool $inherited = false,
        public ?string $inheritanceForeignKey = null,
        public ?string $mappingDefinition = null,
        public ?string $mappingLocalColumn = null,
        public ?string $mappingReferenceColumn = null,
        public ?OnDelete $onDelete = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'property' => $this->propertyName,
            'targetDefinition' => $this->targetDefinition,
            'localField' => $this->localField,
            'referenceField' => $this->referenceField,
            'autoload' => $this->autoload,
            'api' => $this->api->value,
            'inherited' => $this->inherited,
            'inheritanceForeignKey' => $this->inheritanceForeignKey,
            'mappingDefinition' => $this->mappingDefinition,
            'mappingLocalColumn' => $this->mappingLocalColumn,
            'mappingReferenceColumn' => $this->mappingReferenceColumn,
            'onDelete' => $this->onDelete?->value,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['type', 'property', 'targetDefinition', 'localField', 'referenceField'] as $key) {
            if (!\is_string($data[$key] ?? null)) {
                throw new \InvalidArgumentException(\sprintf('Association snapshot key "%s" must be a string.', $key));
            }
        }

        if (!\is_bool($data['autoload'] ?? null) || !\is_string($data['api'] ?? null) || !\is_bool($data['inherited'] ?? null)) {
            throw new \InvalidArgumentException('Association snapshot flags are invalid.');
        }

        return new self(
            type: AssociationType::from($data['type']),
            propertyName: $data['property'],
            targetDefinition: $data['targetDefinition'],
            localField: $data['localField'],
            referenceField: $data['referenceField'],
            autoload: $data['autoload'],
            api: ApiScope::from($data['api']),
            inherited: $data['inherited'],
            inheritanceForeignKey: self::nullableString($data, 'inheritanceForeignKey'),
            mappingDefinition: self::nullableClassString($data, 'mappingDefinition'),
            mappingLocalColumn: self::nullableString($data, 'mappingLocalColumn'),
            mappingReferenceColumn: self::nullableString($data, 'mappingReferenceColumn'),
            onDelete: self::nullableOnDelete($data, 'onDelete'),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !\is_string($value)) {
            throw new \InvalidArgumentException(\sprintf('Association snapshot key "%s" must be a string or null.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $data
     * @return class-string|null
     */
    private static function nullableClassString(array $data, string $key): ?string
    {
        $value = self::nullableString($data, $key);

        /** @var class-string|null $value Historical snapshots may reference a removed mapping definition. */
        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function nullableOnDelete(array $data, string $key): ?OnDelete
    {
        $value = self::nullableString($data, $key);

        return $value === null ? null : OnDelete::from($value);
    }
}
