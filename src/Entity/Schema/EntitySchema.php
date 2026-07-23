<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Schema;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

final readonly class EntitySchema
{
    /**
     * @param class-string<EntityDefinition> $definitionClass
     * @param class-string<Entity> $entityClass
     * @param class-string $collectionClass
     * @param class-string<EntityDefinition>|null $parentDefinition
     * @param class-string<EntityDefinition>|null $translationDefinition
     * @param non-empty-string $entityName
     * @param list<FieldSchema> $fields
     * @param list<AssociationSchema> $associations
     * @param list<IndexSchema> $indexes
     * @param list<ForeignKeyConstraintSchema> $foreignKeys
     */
    public function __construct(
        public string $definitionClass,
        public string $entityClass,
        public string $collectionClass,
        public string $entityName,
        public array $fields,
        public array $associations,
        public array $indexes,
        public array $foreignKeys = [],
        public ?string $parentDefinition = null,
        public ?string $translationDefinition = null,
        public bool $inheritanceAware = false,
        public ?string $renamedFrom = null,
        public string $engine = 'InnoDB',
        public string $charset = 'utf8mb4',
        public string $collation = 'utf8mb4_unicode_ci',
    ) {
    }

    public function field(string $propertyName): FieldSchema
    {
        foreach ($this->fields as $field) {
            if ($field->propertyName === $propertyName) {
                return $field;
            }
        }

        throw new \InvalidArgumentException(\sprintf('Entity "%s" has no field "%s".', $this->entityName, $propertyName));
    }

    /**
     * @return list<FieldSchema>
     */
    public function columns(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (FieldSchema $field): bool => !$field->runtime && !$field->translated,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'definitionClass' => $this->definitionClass,
            'entityClass' => $this->entityClass,
            'collectionClass' => $this->collectionClass,
            'entityName' => $this->entityName,
            'parentDefinition' => $this->parentDefinition,
            'translationDefinition' => $this->translationDefinition,
            'inheritanceAware' => $this->inheritanceAware,
            'renamedFrom' => $this->renamedFrom,
            'engine' => $this->engine,
            'charset' => $this->charset,
            'collation' => $this->collation,
            'fields' => array_map(static fn (FieldSchema $field): array => $field->toArray(), $this->fields),
            'associations' => array_map(static fn (AssociationSchema $association): array => $association->toArray(), $this->associations),
            'indexes' => array_map(static fn (IndexSchema $index): array => $index->toArray(), $this->indexes),
            'foreignKeys' => array_map(static fn (ForeignKeyConstraintSchema $foreignKey): array => $foreignKey->toArray(), $this->foreignKeys),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['definitionClass', 'entityClass', 'collectionClass', 'entityName', 'engine', 'charset', 'collation'] as $key) {
            if (!\is_string($data[$key] ?? null)) {
                throw new \InvalidArgumentException(\sprintf('Entity snapshot key "%s" must be a string.', $key));
            }
        }
        if (!\is_array($data['fields'] ?? null) || !\is_array($data['associations'] ?? null) || !\is_array($data['indexes'] ?? null) || !\is_array($data['foreignKeys'] ?? null)) {
            throw new \InvalidArgumentException('Entity snapshot fields, associations, indexes and foreign keys must be arrays.');
        }
        if (!\is_bool($data['inheritanceAware'] ?? null)) {
            throw new \InvalidArgumentException('Entity snapshot inheritanceAware must be a bool.');
        }

        return new self(
            definitionClass: self::definitionClass($data, 'definitionClass'),
            entityClass: self::entityClass($data, 'entityClass'),
            collectionClass: self::classString($data, 'collectionClass'),
            entityName: $data['entityName'],
            fields: array_values(array_map(self::fieldFromArray(...), $data['fields'])),
            associations: array_values(array_map(self::associationFromArray(...), $data['associations'])),
            indexes: array_values(array_map(self::indexFromArray(...), $data['indexes'])),
            foreignKeys: array_values(array_map(self::foreignKeyFromArray(...), $data['foreignKeys'])),
            parentDefinition: self::nullableDefinitionClass($data, 'parentDefinition'),
            translationDefinition: self::nullableDefinitionClass($data, 'translationDefinition'),
            inheritanceAware: $data['inheritanceAware'],
            renamedFrom: self::nullableString($data, 'renamedFrom'),
            engine: $data['engine'],
            charset: $data['charset'],
            collation: $data['collation'],
        );
    }

    private static function fieldFromArray(mixed $data): FieldSchema
    {
        if (!\is_array($data)) {
            throw new \InvalidArgumentException('Entity snapshot field must be an object.');
        }

        return FieldSchema::fromArray($data);
    }

    private static function associationFromArray(mixed $data): AssociationSchema
    {
        if (!\is_array($data)) {
            throw new \InvalidArgumentException('Entity snapshot association must be an object.');
        }

        return AssociationSchema::fromArray($data);
    }

    private static function indexFromArray(mixed $data): IndexSchema
    {
        if (!\is_array($data)) {
            throw new \InvalidArgumentException('Entity snapshot index must be an object.');
        }

        return IndexSchema::fromArray($data);
    }

    private static function foreignKeyFromArray(mixed $data): ForeignKeyConstraintSchema
    {
        if (!\is_array($data)) {
            throw new \InvalidArgumentException('Entity snapshot foreign key must be an object.');
        }

        return ForeignKeyConstraintSchema::fromArray($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !\is_string($value)) {
            throw new \InvalidArgumentException(\sprintf('Entity snapshot key "%s" must be a string or null.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $data
     * @return class-string
     */
    private static function classString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value) || $value === '') {
            throw new \InvalidArgumentException(\sprintf('Entity snapshot key "%s" must contain a class name.', $key));
        }

        /** @var class-string $value Historical snapshots intentionally allow classes no longer present in the codebase. */
        return $value;
    }

    /** @param array<string, mixed> $data
     * @return class-string<EntityDefinition>
     */
    private static function definitionClass(array $data, string $key): string
    {
        $value = self::classString($data, $key);

        /** @var class-string<EntityDefinition> $value */
        return $value;
    }

    /** @param array<string, mixed> $data
     * @return class-string<Entity>
     */
    private static function entityClass(array $data, string $key): string
    {
        $value = self::classString($data, $key);

        /** @var class-string<Entity> $value */
        return $value;
    }

    /** @param array<string, mixed> $data
     * @return class-string<EntityDefinition>|null
     */
    private static function nullableDefinitionClass(array $data, string $key): ?string
    {
        $value = self::nullableString($data, $key);

        /** @var class-string<EntityDefinition>|null $value */
        return $value;
    }
}
