<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity;

use Frosh\Jetpack\Entity\Schema\BundleSchema;
use Frosh\Jetpack\Entity\Schema\EntitySchema;
use Shopware\Core\Framework\Bundle;

/**
 * @internal
 */
final class BundleEntitySchemaProvider
{
    /**
     * @param iterable<JetpackDefinition> $definitions
     */
    public function __construct(private readonly iterable $definitions)
    {
    }

    public function forBundle(Bundle $bundle): BundleSchema
    {
        $resolvedBundlePath = realpath($bundle->getPath());
        $bundlePath = $resolvedBundlePath === false ? $bundle->getPath() : $resolvedBundlePath;
        $entities = [];
        $allSchemas = [];
        foreach ($this->definitions as $definition) {
            $schema = $definition->getJetpackSchema();
            $allSchemas[] = $schema;
            $reflection = new \ReflectionClass($schema->definitionClass);
            $file = $reflection->getFileName();
            if ($file === false) {
                continue;
            }
            $resolvedFile = realpath($file);
            $file = $resolvedFile === false ? $file : $resolvedFile;
            if (!str_starts_with($file, rtrim($bundlePath, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR)) {
                continue;
            }
            $entities[] = $schema;
        }

        $this->assertUnique($allSchemas);
        $this->assertReferences($allSchemas);
        usort($entities, static fn (EntitySchema $a, EntitySchema $b): int => $a->entityName <=> $b->entityName);

        return new BundleSchema($bundle->getName(), $entities);
    }

    /**
     * @return list<EntitySchema>
     */
    public function all(): array
    {
        $schemas = [];
        foreach ($this->definitions as $definition) {
            $schemas[] = $definition->getJetpackSchema();
        }

        $this->assertUnique($schemas);
        $this->assertReferences($schemas);
        usort($schemas, static fn (EntitySchema $a, EntitySchema $b): int => $a->entityName <=> $b->entityName);

        return $schemas;
    }

    /**
     * @param list<EntitySchema> $schemas
     */
    private function assertUnique(array $schemas): void
    {
        $entityNames = [];
        $definitions = [];
        $foreignKeys = [];
        foreach ($schemas as $schema) {
            if (isset($entityNames[$schema->entityName])) {
                throw new EntitySchemaException(\sprintf('Jetpack entity name "%s" is declared more than once.', $schema->entityName));
            }
            if (isset($definitions[$schema->definitionClass])) {
                throw new EntitySchemaException(\sprintf('Jetpack definition "%s" was registered more than once.', $schema->definitionClass));
            }
            $entityNames[$schema->entityName] = true;
            $definitions[$schema->definitionClass] = true;
            foreach ($schema->foreignKeys as $foreignKey) {
                if (isset($foreignKeys[$foreignKey->name])) {
                    throw new EntitySchemaException(\sprintf('Jetpack foreign-key name "%s" is declared by more than one entity.', $foreignKey->name));
                }
                $foreignKeys[$foreignKey->name] = true;
            }
        }
    }

    /**
     * @param list<EntitySchema> $schemas
     */
    private function assertReferences(array $schemas): void
    {
        $byDefinition = [];
        foreach ($schemas as $schema) {
            $byDefinition[$schema->definitionClass] = $schema;
        }
        foreach ($schemas as $schema) {
            foreach ($schema->foreignKeys as $foreignKey) {
                $target = $byDefinition[$foreignKey->targetDefinition] ?? null;
                if (!$target instanceof EntitySchema) {
                    continue;
                }
                foreach ($foreignKey->referenceFields as $referenceField) {
                    try {
                        $field = $target->field($referenceField);
                    } catch (\InvalidArgumentException) {
                        throw new EntitySchemaException(\sprintf(
                            'Foreign key "%s" references missing field "%s.%s".',
                            $foreignKey->name,
                            $target->entityName,
                            $referenceField,
                        ));
                    }
                    if ($field->runtime || $field->translated) {
                        throw new EntitySchemaException(\sprintf(
                            'Foreign key "%s" references non-persistent field "%s.%s".',
                            $foreignKey->name,
                            $target->entityName,
                            $referenceField,
                        ));
                    }
                }
            }
        }
    }
}
