<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity\Schema;

final readonly class BundleSchema
{
    public const FORMAT_VERSION = 1;

    /**
     * @param list<EntitySchema> $entities
     */
    public function __construct(
        public string $bundleName,
        public array $entities,
    ) {
    }

    public static function empty(string $bundleName): self
    {
        return new self($bundleName, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $entities = $this->entities;
        usort($entities, static fn (EntitySchema $a, EntitySchema $b): int => $a->entityName <=> $b->entityName);

        return [
            'formatVersion' => self::FORMAT_VERSION,
            'bundle' => $this->bundleName,
            'entities' => array_map(static fn (EntitySchema $entity): array => $entity->toArray(), $entities),
        ];
    }

    public function fingerprint(): string
    {
        $schema = $this->toArray();
        unset($schema['bundle']);

        return hash('sha256', json_encode($schema, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));
    }

    public function entityByTable(string $table): ?EntitySchema
    {
        foreach ($this->entities as $entity) {
            if ($entity->entityName === $table) {
                return $entity;
            }
        }

        return null;
    }

    public function entityByDefinition(string $definitionClass): ?EntitySchema
    {
        foreach ($this->entities as $entity) {
            if ($entity->definitionClass === $definitionClass) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (($data['formatVersion'] ?? null) !== self::FORMAT_VERSION) {
            throw new \InvalidArgumentException('Unsupported Jetpack entity snapshot format.');
        }
        if (!\is_string($data['bundle'] ?? null) || !\is_array($data['entities'] ?? null)) {
            throw new \InvalidArgumentException('Invalid Jetpack bundle schema snapshot.');
        }

        $entities = [];
        foreach ($data['entities'] as $entity) {
            if (!\is_array($entity)) {
                throw new \InvalidArgumentException('Bundle schema entities must be objects.');
            }
            $entities[] = EntitySchema::fromArray($entity);
        }

        return new self($data['bundle'], $entities);
    }
}
