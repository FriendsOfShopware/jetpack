<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity;

final class EntitySchemaException extends \RuntimeException
{
    public static function definitionAttributeMissing(string $definition): self
    {
        return new self(\sprintf('Jetpack definition "%s" needs exactly one #[Entity] attribute.', $definition));
    }

    public static function invalidEntityClass(string $definition, string $entity): self
    {
        return new self(\sprintf('Jetpack definition "%s" references "%s", which is not a DAL Entity.', $definition, $entity));
    }

    public static function fieldTypeCannotBeInferred(string $entity, string $property): self
    {
        return new self(\sprintf('Cannot infer the field type for %s::$%s; configure Field::type explicitly.', $entity, $property));
    }

    public static function duplicateStorageName(string $entityName, string $storageName): self
    {
        return new self(\sprintf('Entity "%s" contains duplicate storage column "%s".', $entityName, $storageName));
    }

    public static function primaryKeyMissing(string $entityName): self
    {
        return new self(\sprintf('Entity "%s" needs at least one #[Id] or primary-key field.', $entityName));
    }

    public static function associationLocalFieldMissing(string $entityName, string $property, string $localField): self
    {
        return new self(\sprintf('Association "%s.%s" references missing local field "%s".', $entityName, $property, $localField));
    }

    public static function foreignKeyTargetMismatch(string $entityName, string $association, string $fieldTarget, string $associationTarget): self
    {
        return new self(\sprintf(
            'Association "%s.%s" targets "%s" while its foreign key targets "%s".',
            $entityName,
            $association,
            $associationTarget,
            $fieldTarget,
        ));
    }

    public static function setNullNeedsNullableField(string $entityName, string $property): self
    {
        return new self(\sprintf('Foreign key "%s.%s" uses SET NULL but is not nullable.', $entityName, $property));
    }

    public static function indexFieldMissing(string $entityName, string $field): self
    {
        return new self(\sprintf('Index on entity "%s" references missing or non-persistent field "%s".', $entityName, $field));
    }

    public static function translatedFieldNeedsDefinition(string $entityName): self
    {
        return new self(\sprintf('Entity "%s" contains translated fields but has no translationDefinition.', $entityName));
    }
}
