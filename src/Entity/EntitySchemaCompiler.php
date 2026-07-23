<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity;

use Frosh\Jetpack\Attribute\ApiScope;
use Frosh\Jetpack\Attribute\Entity as EntityAttribute;
use Frosh\Jetpack\Attribute\Field as FieldAttribute;
use Frosh\Jetpack\Attribute\FieldType;
use Frosh\Jetpack\Attribute\ForeignKey;
use Frosh\Jetpack\Attribute\Index;
use Frosh\Jetpack\Attribute\ManyToMany;
use Frosh\Jetpack\Attribute\ManyToOne;
use Frosh\Jetpack\Attribute\MappingEntity;
use Frosh\Jetpack\Attribute\OnDelete;
use Frosh\Jetpack\Attribute\OneToMany;
use Frosh\Jetpack\Attribute\OneToOne;
use Frosh\Jetpack\Attribute\ReferenceVersion;
use Frosh\Jetpack\Attribute\TranslationEntity;
use Frosh\Jetpack\Entity\Field\FieldFactory;
use Frosh\Jetpack\Entity\Schema\AssociationSchema;
use Frosh\Jetpack\Entity\Schema\AssociationType;
use Frosh\Jetpack\Entity\Schema\EntitySchema;
use Frosh\Jetpack\Entity\Schema\FieldSchema;
use Frosh\Jetpack\Entity\Schema\ForeignKeyConstraintSchema;
use Frosh\Jetpack\Entity\Schema\IndexSchema;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\Language\LanguageDefinition;

final class EntitySchemaCompiler
{
    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    public function compile(string $definitionClass): EntitySchema
    {
        $definition = new \ReflectionClass($definitionClass);
        $attributes = $definition->getAttributes(EntityAttribute::class);
        $translation = $definition->getAttributes(TranslationEntity::class);
        $mapping = $definition->getAttributes(MappingEntity::class);
        $declarationCount = \count($attributes) + \count($translation) + \count($mapping);
        if ($declarationCount === 0) {
            throw EntitySchemaException::definitionAttributeMissing($definitionClass);
        }
        if ($declarationCount !== 1) {
            throw new EntitySchemaException(\sprintf('Jetpack definition "%s" must declare exactly one entity, translation, or mapping attribute.', $definitionClass));
        }
        if ($translation !== []) {
            return $this->compileTranslation($definitionClass, $translation[0]->newInstance());
        }
        if ($mapping !== []) {
            return $this->compileMapping($definition, $definitionClass, $mapping[0]->newInstance());
        }

        $entityAttribute = $attributes[0]->newInstance();
        if (!$definition->isSubclassOf(JetpackEntityDefinition::class)) {
            throw new EntitySchemaException(\sprintf('Jetpack entity definition "%s" must extend %s.', $definitionClass, JetpackEntityDefinition::class));
        }
        if ($entityAttribute->name === '') {
            throw new EntitySchemaException(\sprintf('Jetpack definition "%s" needs a non-empty entity name.', $definitionClass));
        }
        if ($entityAttribute->renamedFrom !== null) {
            $this->assertSqlIdentifier($entityAttribute->renamedFrom, 'renamed table');
        }
        $this->assertSqlIdentifier($entityAttribute->engine, 'storage engine');
        $this->assertSqlIdentifier($entityAttribute->charset, 'charset');
        $this->assertSqlIdentifier($entityAttribute->collation, 'collation');
        if (!\is_subclass_of($entityAttribute->entity, Entity::class)) {
            throw EntitySchemaException::invalidEntityClass($definitionClass, $entityAttribute->entity);
        }
        if (!is_a($entityAttribute->collection, EntityCollection::class, true)) {
            throw new EntitySchemaException(\sprintf('Jetpack definition "%s" references invalid collection "%s".', $definitionClass, $entityAttribute->collection));
        }
        if ($entityAttribute->parent !== null && !is_a($entityAttribute->parent, EntityDefinition::class, true)) {
            throw new EntitySchemaException(\sprintf('Jetpack definition "%s" references invalid parent definition "%s".', $definitionClass, $entityAttribute->parent));
        }
        if ($entityAttribute->translationDefinition !== null && !is_a($entityAttribute->translationDefinition, JetpackTranslationDefinition::class, true)) {
            throw new EntitySchemaException(\sprintf('Jetpack definition "%s" references invalid Jetpack translation definition "%s".', $definitionClass, $entityAttribute->translationDefinition));
        }

        $fields = $this->compileFields($entityAttribute->entity);
        if ($entityAttribute->versioned) {
            $fields[] = new FieldSchema(
                propertyName: 'versionId',
                storageName: 'version_id',
                type: FieldType::Version,
                nullable: false,
                required: true,
                primaryKey: true,
                api: ApiScope::All,
            );
        }
        $associations = $this->compileAssociations($entityAttribute->entity);

        $fields[] = new FieldSchema(
            propertyName: 'createdAt',
            storageName: 'created_at',
            type: FieldType::DateTime,
            nullable: false,
            required: true,
            primaryKey: false,
            api: ApiScope::All,
            defaultField: true,
        );
        $fields[] = new FieldSchema(
            propertyName: 'updatedAt',
            storageName: 'updated_at',
            type: FieldType::DateTime,
            nullable: true,
            required: false,
            primaryKey: false,
            api: ApiScope::All,
            defaultField: true,
        );
        $this->validatePhysicalFields($entityAttribute->name, $fields);
        $this->validate($definitionClass, $entityAttribute, $fields, $associations);

        return new EntitySchema(
            definitionClass: $definitionClass,
            entityClass: $entityAttribute->entity,
            collectionClass: $entityAttribute->collection,
            entityName: $entityAttribute->name,
            fields: $fields,
            associations: $associations,
            indexes: $this->compileIndexes($definition, $entityAttribute->name, $fields),
            foreignKeys: $this->compileForeignKeys($entityAttribute->name, $fields),
            parentDefinition: $entityAttribute->parent,
            translationDefinition: $entityAttribute->translationDefinition,
            inheritanceAware: $entityAttribute->inheritanceAware,
            renamedFrom: $entityAttribute->renamedFrom,
            engine: $entityAttribute->engine,
            charset: $entityAttribute->charset,
            collation: $entityAttribute->collation,
        );
    }

    /**
     * @param class-string<Entity> $entityClass
     *
     * @return list<FieldSchema>
     */
    private function compileFields(string $entityClass): array
    {
        $fields = [];
        foreach ((new \ReflectionClass($entityClass))->getProperties() as $property) {
            $attributes = $property->getAttributes(FieldAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);
            if ($attributes === []) {
                continue;
            }
            if (\count($attributes) > 1) {
                throw new EntitySchemaException(\sprintf('Property %s::$%s has more than one Jetpack field attribute.', $entityClass, $property->getName()));
            }

            /** @var FieldAttribute $attribute */
            $attribute = $attributes[0]->newInstance();
            if ($attribute->factory !== null && $attribute->type !== null && $attribute->type !== FieldType::Custom) {
                throw new EntitySchemaException(\sprintf('Field %s::$%s cannot combine a factory with a non-custom field type.', $entityClass, $property->getName()));
            }
            $type = $attribute->factory === null
                ? ($attribute->type ?? $this->inferType($entityClass, $property))
                : FieldType::Custom;
            if ($type === FieldType::Version) {
                throw new EntitySchemaException(\sprintf('Field %s::$%s cannot declare a Version field; use Entity::versioned instead.', $entityClass, $property->getName()));
            }
            if ($type === FieldType::ReferenceVersion && !$attribute instanceof ReferenceVersion) {
                throw new EntitySchemaException(\sprintf('Field %s::$%s must use the ReferenceVersion attribute.', $entityClass, $property->getName()));
            }
            $propertyType = $property->getType();
            $nullable = $propertyType?->allowsNull() ?? true;
            $required = $attribute->required ?? !$nullable;
            $storageName = $attribute->column ?? self::snakeCase($property->getName());
            $enumClass = $attribute->enum;
            if ($type === FieldType::Enum && $enumClass === null) {
                $enumClass = $this->inferEnumClass($property);
            }
            if ($type === FieldType::Enum && ($enumClass === null || !enum_exists($enumClass) || !is_subclass_of($enumClass, \BackedEnum::class))) {
                throw new EntitySchemaException(\sprintf('Enum field %s::$%s must reference a backed enum.', $entityClass, $property->getName()));
            }
            if ($type === FieldType::Enum && (!$propertyType instanceof \ReflectionNamedType || $propertyType->getName() !== $enumClass)) {
                throw new EntitySchemaException(\sprintf('Enum field %s::$%s must use %s as its PHP property type.', $entityClass, $property->getName(), $enumClass));
            }
            $this->validatePropertyType($entityClass, $property, $type);
            $fieldFactory = $attribute->factory;
            if ($type === FieldType::Custom) {
                if ($fieldFactory === null || !is_a($fieldFactory, FieldFactory::class, true)) {
                    throw new EntitySchemaException(\sprintf('Custom field %s::$%s must reference a Jetpack FieldFactory.', $entityClass, $property->getName()));
                }
                $factoryReflection = new \ReflectionClass($fieldFactory);
                if (!$factoryReflection->isInstantiable() || ($factoryReflection->getConstructor()?->getNumberOfRequiredParameters() ?? 0) > 0) {
                    throw new EntitySchemaException(\sprintf('Field factory "%s" must be instantiable without constructor arguments.', $fieldFactory));
                }
            }
            $referenceDefinition = null;
            $referenceField = 'id';
            $onDelete = OnDelete::Restrict;
            $constraintName = null;
            $referenceVersionFor = null;

            if ($attribute instanceof ForeignKey) {
                $this->assertDefinitionClass($attribute->target, $entityClass . '::$' . $property->getName());
                $referenceDefinition = $attribute->target;
                $referenceField = $attribute->referenceField;
                $onDelete = $attribute->onDelete;
                $constraintName = $attribute->constraintName;
                if ($onDelete === OnDelete::SetNull && !$nullable) {
                    throw EntitySchemaException::setNullNeedsNullableField($entityClass, $property->getName());
                }
            } elseif ($attribute instanceof ReferenceVersion) {
                $this->assertDefinitionClass($attribute->target, $entityClass . '::$' . $property->getName());
                $referenceDefinition = $attribute->target;
                $referenceVersionFor = $attribute->forField;
            }

            $fields[] = $this->withResolvedSqlType(new FieldSchema(
                propertyName: $property->getName(),
                storageName: $storageName,
                type: $type,
                nullable: $nullable,
                required: $required,
                primaryKey: $attribute->primaryKey,
                api: $attribute->api,
                length: $attribute->length,
                writeProtected: $attribute->writeProtected,
                runtime: $attribute->runtime,
                computed: $attribute->computed,
                inherited: $attribute->inherited,
                inheritanceForeignKey: $attribute->inheritanceForeignKey,
                searchRanking: $attribute->searchRanking,
                tokenize: $attribute->tokenize,
                allowHtml: $attribute->allowHtml,
                allowEmptyString: $attribute->allowEmptyString,
                translated: $attribute->translated,
                hasDefault: $attribute->hasDefault,
                default: $attribute->default,
                renamedFrom: $attribute->renamedFrom,
                enumClass: $enumClass,
                fieldFactory: $fieldFactory,
                referenceDefinition: $referenceDefinition,
                referenceField: $referenceField,
                onDelete: $onDelete,
                constraintName: $constraintName,
                referenceVersionFor: $referenceVersionFor,
            ));
        }

        return $fields;
    }

    private function withResolvedSqlType(FieldSchema $field): FieldSchema
    {
        $sqlType = match ($field->type) {
            FieldType::Enum => $this->enumSqlType($field),
            FieldType::Custom => $this->customSqlType($field),
            default => null,
        };
        if ($sqlType === null) {
            return $field;
        }
        $this->assertSafeSqlType($sqlType, $field->propertyName);

        return FieldSchema::fromArray([
            ...$field->toArray(),
            'sqlType' => $sqlType,
        ]);
    }

    private function enumSqlType(FieldSchema $field): string
    {
        $class = $field->enumClass;
        if ($class === null || !enum_exists($class)) {
            throw new EntitySchemaException(\sprintf('Enum field "%s" has no valid enum class.', $field->propertyName));
        }
        $type = (new \ReflectionEnum($class))->getBackingType()?->getName();

        return $type === 'int' ? 'INT' : 'VARCHAR(' . ($field->length ?? 255) . ')';
    }

    private function customSqlType(FieldSchema $field): string
    {
        $factoryClass = $field->fieldFactory;
        if ($factoryClass === null || !is_a($factoryClass, FieldFactory::class, true)) {
            throw new EntitySchemaException(\sprintf('Custom field "%s" has no valid field factory.', $field->propertyName));
        }

        return trim((new $factoryClass())->sqlType($field));
    }

    private function assertSafeSqlType(string $sqlType, string $field): void
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*(?:\(\s*[1-9][0-9]*(?:\s*,\s*[0-9]+)?\s*\))?(?:\s+(?:UNSIGNED|ZEROFILL))*$/i', $sqlType) !== 1) {
            throw new EntitySchemaException(\sprintf('Field "%s" resolved to an unsafe SQL type.', $field));
        }
    }

    /**
     * @param class-string<Entity> $entityClass
     *
     * @return list<AssociationSchema>
     */
    private function compileAssociations(string $entityClass): array
    {
        $associations = [];
        foreach ((new \ReflectionClass($entityClass))->getProperties() as $property) {
            $associationCount = 0;
            foreach ([ManyToOne::class, OneToOne::class, OneToMany::class, ManyToMany::class] as $associationClass) {
                $associationCount += \count($property->getAttributes($associationClass));
            }
            if ($associationCount > 1) {
                throw new EntitySchemaException(\sprintf('Property %s::$%s has more than one Jetpack association attribute.', $entityClass, $property->getName()));
            }
            if ($associationCount > 0 && $property->getAttributes(FieldAttribute::class, \ReflectionAttribute::IS_INSTANCEOF) !== []) {
                throw new EntitySchemaException(\sprintf('Property %s::$%s cannot be both a field and an association.', $entityClass, $property->getName()));
            }
            if (($attribute = $this->firstAttribute($property, ManyToOne::class)) instanceof ManyToOne) {
                $this->assertDefinitionClass($attribute->target, $entityClass . '::$' . $property->getName());
                $associations[] = new AssociationSchema(AssociationType::ManyToOne, $property->getName(), $attribute->target, $attribute->localField, $attribute->referenceField, $attribute->autoload, $attribute->api, $attribute->inherited, $attribute->inheritanceForeignKey);
            } elseif (($attribute = $this->firstAttribute($property, OneToOne::class)) instanceof OneToOne) {
                $this->assertDefinitionClass($attribute->target, $entityClass . '::$' . $property->getName());
                $associations[] = new AssociationSchema(AssociationType::OneToOne, $property->getName(), $attribute->target, $attribute->localField, $attribute->referenceField, $attribute->autoload, $attribute->api, $attribute->inherited, $attribute->inheritanceForeignKey);
            } elseif (($attribute = $this->firstAttribute($property, OneToMany::class)) instanceof OneToMany) {
                $this->assertDefinitionClass($attribute->target, $entityClass . '::$' . $property->getName());
                $associations[] = new AssociationSchema(AssociationType::OneToMany, $property->getName(), $attribute->target, $attribute->localField, $attribute->referenceField, api: $attribute->api, inherited: $attribute->inherited, inheritanceForeignKey: $attribute->inheritanceForeignKey, onDelete: $attribute->onDelete);
            } elseif (($attribute = $this->firstAttribute($property, ManyToMany::class)) instanceof ManyToMany) {
                $this->assertDefinitionClass($attribute->target, $entityClass . '::$' . $property->getName());
                if (!is_a($attribute->mappingDefinition, MappingEntityDefinition::class, true)) {
                    throw new EntitySchemaException(\sprintf('Association %s::$%s references invalid mapping definition "%s".', $entityClass, $property->getName(), $attribute->mappingDefinition));
                }
                $associations[] = new AssociationSchema(
                    AssociationType::ManyToMany,
                    $property->getName(),
                    $attribute->target,
                    $attribute->sourceColumn,
                    $attribute->referenceField,
                    api: $attribute->api,
                    inherited: $attribute->inherited,
                    inheritanceForeignKey: $attribute->inheritanceForeignKey,
                    mappingDefinition: $attribute->mappingDefinition,
                    mappingLocalColumn: $attribute->mappingLocalColumn,
                    mappingReferenceColumn: $attribute->mappingReferenceColumn,
                    onDelete: $attribute->onDelete,
                );
            }
        }

        return $associations;
    }

    /**
     * @param class-string $attributeClass
     */
    private function firstAttribute(\ReflectionProperty $property, string $attributeClass): ?object
    {
        $attributes = $property->getAttributes($attributeClass);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     * @param list<FieldSchema> $fields
     * @param list<AssociationSchema> $associations
     */
    private function validate(string $definitionClass, EntityAttribute $entity, array $fields, array $associations): void
    {
        $properties = [];
        $hasTranslated = false;
        foreach ($fields as $field) {
            $properties[$field->propertyName] = $field;
            $hasTranslated = $hasTranslated || $field->translated;
            if ($field->translated && $field->runtime) {
                throw new EntitySchemaException(\sprintf('Field "%s.%s" cannot be both translated and runtime.', $entity->name, $field->propertyName));
            }
        }
        if ($hasTranslated && $entity->translationDefinition === null) {
            throw EntitySchemaException::translatedFieldNeedsDefinition($entity->name);
        }
        if ($entity->parent !== null) {
            $hasParentForeignKey = false;
            foreach ($fields as $field) {
                $hasParentForeignKey = $hasParentForeignKey
                    || ($field->type !== FieldType::ReferenceVersion && $field->referenceDefinition === $entity->parent);
            }
            if (!$hasParentForeignKey) {
                throw new EntitySchemaException(\sprintf('Aggregate entity "%s" needs a foreign key to parent definition "%s".', $entity->name, $entity->parent));
            }
        }
        if ($entity->inheritanceAware) {
            $parentId = $properties['parentId'] ?? null;
            if (!$parentId instanceof FieldSchema
                || $parentId->storageName !== 'parent_id'
                || !$parentId->nullable
                || $parentId->referenceDefinition !== $definitionClass
                || $parentId->onDelete !== OnDelete::Cascade
            ) {
                throw new EntitySchemaException(\sprintf('Inheritance-aware entity "%s" needs a nullable self-referencing parentId foreign key with CASCADE.', $entity->name));
            }
            $parentAssociation = null;
            foreach ($associations as $association) {
                if ($association->propertyName === 'parent') {
                    $parentAssociation = $association;
                    break;
                }
            }
            if (!$parentAssociation instanceof AssociationSchema
                || $parentAssociation->type !== AssociationType::ManyToOne
                || $parentAssociation->localField !== 'parentId'
                || $parentAssociation->targetDefinition !== $definitionClass
            ) {
                throw new EntitySchemaException(\sprintf('Inheritance-aware entity "%s" needs a self-referencing parent association.', $entity->name));
            }
            $childrenAssociation = null;
            foreach ($associations as $association) {
                if ($association->type === AssociationType::OneToMany
                    && $association->targetDefinition === $definitionClass
                    && $association->referenceField === 'parentId'
                ) {
                    $childrenAssociation = $association;
                    break;
                }
            }
            if (!$childrenAssociation instanceof AssociationSchema) {
                throw new EntitySchemaException(\sprintf('Inheritance-aware entity "%s" needs a self-referencing children association.', $entity->name));
            }
            $versioned = false;
            $hasParentReferenceVersion = false;
            foreach ($fields as $field) {
                $versioned = $versioned || $field->type === FieldType::Version;
                $hasParentReferenceVersion = $hasParentReferenceVersion
                    || ($field->type === FieldType::ReferenceVersion
                        && $field->referenceDefinition === $definitionClass
                        && $field->referenceVersionFor === 'parentId');
            }
            if ($versioned && !$hasParentReferenceVersion) {
                throw new EntitySchemaException(\sprintf('Versioned inheritance-aware entity "%s" needs a reference-version field for parentId.', $entity->name));
            }
        }

        $foreignKeysByTarget = [];
        foreach ($fields as $field) {
            if ($field->referenceDefinition !== null && $field->type !== FieldType::ReferenceVersion) {
                $foreignKeysByTarget[$field->referenceDefinition][] = $field;
            }
        }
        $referenceVersionKeys = [];
        foreach ($fields as $field) {
            if ($field->type !== FieldType::ReferenceVersion || $field->referenceDefinition === null) {
                continue;
            }
            $candidates = $foreignKeysByTarget[$field->referenceDefinition] ?? [];
            if ($field->referenceVersionFor === null && \count($candidates) !== 1) {
                throw new EntitySchemaException(\sprintf('Reference-version field "%s.%s" must set forField because its target is used by %d foreign keys.', $entity->name, $field->propertyName, \count($candidates)));
            }
            $forField = $field->referenceVersionFor ?? $candidates[0]->propertyName;
            $foreignKey = $properties[$forField] ?? null;
            if (!$foreignKey instanceof FieldSchema || $foreignKey->referenceDefinition !== $field->referenceDefinition || $foreignKey->type === FieldType::ReferenceVersion) {
                throw new EntitySchemaException(\sprintf('Reference-version field "%s.%s" references invalid foreign key "%s".', $entity->name, $field->propertyName, $forField));
            }
            if ($foreignKey->onDelete === OnDelete::SetNull && !$field->nullable) {
                throw new EntitySchemaException(\sprintf('Reference-version field "%s.%s" must be nullable because foreign key "%s" uses SET NULL.', $entity->name, $field->propertyName, $forField));
            }
            $key = $field->referenceDefinition . ':' . $forField;
            if (isset($referenceVersionKeys[$key])) {
                throw new EntitySchemaException(\sprintf('Entity "%s" has multiple reference-version fields for "%s".', $entity->name, $forField));
            }
            $referenceVersionKeys[$key] = true;
        }

        foreach ($associations as $association) {
            if ($association->type === AssociationType::ManyToMany) {
                $this->validateManyToMany($definitionClass, $entity->name, $association, $properties);
                continue;
            }
            if ($association->type === AssociationType::OneToMany) {
                if (!isset($properties[$association->localField])) {
                    throw EntitySchemaException::associationLocalFieldMissing($entity->name, $association->propertyName, $association->localField);
                }
                continue;
            }
            $local = $properties[$association->localField] ?? null;
            if (!$local instanceof FieldSchema) {
                throw EntitySchemaException::associationLocalFieldMissing($entity->name, $association->propertyName, $association->localField);
            }
            if ($local->referenceDefinition === null) {
                throw EntitySchemaException::associationLocalFieldMissing($entity->name, $association->propertyName, $association->localField);
            }
            if ($local->referenceDefinition !== $association->targetDefinition) {
                throw EntitySchemaException::foreignKeyTargetMismatch($entity->name, $association->propertyName, $local->referenceDefinition, $association->targetDefinition);
            }
            if ($local->referenceField !== $association->referenceField) {
                throw new EntitySchemaException(\sprintf(
                    'Association "%s.%s" references target field "%s", but its foreign key references "%s".',
                    $entity->name,
                    $association->propertyName,
                    $association->referenceField,
                    $local->referenceField,
                ));
            }
        }
    }

    /**
     * @param \ReflectionClass<covariant EntityDefinition> $definition
     * @param list<FieldSchema> $fields
     *
     * @return list<IndexSchema>
     */
    private function compileIndexes(\ReflectionClass $definition, string $entityName, array $fields): array
    {
        $propertyMap = [];
        foreach ($fields as $field) {
            $propertyMap[$field->propertyName] = $field;
        }

        $indexes = [];
        $indexNames = [];
        foreach ($definition->getAttributes(Index::class) as $attribute) {
            $index = $attribute->newInstance();
            if ($index->fields === []) {
                throw new EntitySchemaException(\sprintf('Entity "%s" contains an index without fields.', $entityName));
            }
            $columns = [];
            foreach ($index->fields as $fieldName) {
                $field = $propertyMap[$fieldName] ?? null;
                if (!$field instanceof FieldSchema || $field->runtime || $field->translated) {
                    throw EntitySchemaException::indexFieldMissing($entityName, $fieldName);
                }
                if (\in_array($field->storageName, $columns, true)) {
                    throw new EntitySchemaException(\sprintf('Index on entity "%s" contains duplicate field "%s".', $entityName, $fieldName));
                }
                $columns[] = $field->storageName;
            }
            $prefix = $index->unique ? 'uniq' : 'idx';
            $name = $index->name ?? $prefix . '.' . $entityName . '.' . implode('_', $columns);
            if (isset($indexNames[$name])) {
                throw new EntitySchemaException(\sprintf('Entity "%s" contains duplicate index name "%s".', $entityName, $name));
            }
            $this->assertSqlIdentifier($name, 'index');
            $indexNames[$name] = true;
            $indexes[] = new IndexSchema($name, $columns, $index->unique);
        }

        usort($indexes, static fn (IndexSchema $a, IndexSchema $b): int => $a->name <=> $b->name);

        return $indexes;
    }

    /**
     * @param list<FieldSchema> $fields
     *
     * @return list<ForeignKeyConstraintSchema>
     */
    private function compileForeignKeys(string $entityName, array $fields): array
    {
        $referenceVersions = [];
        foreach ($fields as $field) {
            if ($field->type === FieldType::ReferenceVersion && $field->referenceDefinition !== null) {
                $key = $field->referenceDefinition . ':' . ($field->referenceVersionFor ?? '*');
                $referenceVersions[$key] = $field;
            }
        }

        $constraints = [];
        $constraintNames = [];
        foreach ($fields as $field) {
            if ($field->referenceDefinition === null || $field->type === FieldType::ReferenceVersion || $field->type === FieldType::Version) {
                continue;
            }
            $version = $referenceVersions[$field->referenceDefinition . ':' . $field->propertyName]
                ?? $referenceVersions[$field->referenceDefinition . ':*']
                ?? null;
            $localColumns = [$field->storageName];
            $referenceFields = [$field->referenceField];
            if ($version instanceof FieldSchema) {
                $localColumns[] = $version->storageName;
                $referenceFields[] = 'versionId';
            }
            $name = $field->constraintName ?? 'fk.' . $entityName . '.' . $field->storageName;
            $this->assertSqlIdentifier($name, 'foreign key');
            if (isset($constraintNames[$name])) {
                throw new EntitySchemaException(\sprintf('Entity "%s" contains duplicate foreign-key name "%s".', $entityName, $name));
            }
            $constraintNames[$name] = true;
            $constraints[] = new ForeignKeyConstraintSchema(
                name: $name,
                localColumns: $localColumns,
                targetDefinition: $field->referenceDefinition,
                referenceFields: $referenceFields,
                onDelete: $field->onDelete,
            );
        }

        usort($constraints, static fn (ForeignKeyConstraintSchema $a, ForeignKeyConstraintSchema $b): int => $a->name <=> $b->name);

        return $constraints;
    }

    /**
     * @param class-string<Entity> $entityClass
     */
    private function inferType(string $entityClass, \ReflectionProperty $property): FieldType
    {
        $type = $property->getType();
        if (!$type instanceof \ReflectionNamedType) {
            throw EntitySchemaException::fieldTypeCannotBeInferred($entityClass, $property->getName());
        }

        $name = $type->getName();
        if (enum_exists($name) && is_subclass_of($name, \BackedEnum::class)) {
            return FieldType::Enum;
        }

        return match ($name) {
            'string' => FieldType::String,
            'int' => FieldType::Int,
            'float' => FieldType::Float,
            'bool' => FieldType::Bool,
            'array' => FieldType::Json,
            \DateTimeImmutable::class, \DateTimeInterface::class => FieldType::DateTime,
            default => throw EntitySchemaException::fieldTypeCannotBeInferred($entityClass, $property->getName()),
        };
    }

    /**
     * @param class-string<Entity> $entityClass
     */
    private function validatePropertyType(string $entityClass, \ReflectionProperty $property, FieldType $fieldType): void
    {
        if ($fieldType === FieldType::Custom || $fieldType === FieldType::Enum) {
            return;
        }
        $propertyType = $property->getType();
        if ($propertyType === null) {
            return;
        }
        if (!$propertyType instanceof \ReflectionNamedType) {
            throw new EntitySchemaException(\sprintf(
                'Field %s::$%s uses %s storage, which cannot hydrate PHP type %s.',
                $entityClass,
                $property->getName(),
                $fieldType->value,
                (string) $propertyType,
            ));
        }
        if ($propertyType->getName() === 'mixed') {
            return;
        }
        $propertyTypeName = $propertyType->getName();
        $valid = match ($fieldType) {
            FieldType::Uuid,
            FieldType::String,
            FieldType::Text,
            FieldType::Blob,
            FieldType::Email,
            FieldType::Version,
            FieldType::ReferenceVersion => $propertyTypeName === 'string',
            FieldType::Int => $propertyTypeName === 'int',
            FieldType::Float => $propertyTypeName === 'float',
            FieldType::Bool => $propertyTypeName === 'bool',
            FieldType::DateTime, FieldType::Date => is_a(\DateTimeImmutable::class, $propertyTypeName, true),
            FieldType::Json => $propertyTypeName === 'array',
            FieldType::Price => is_a(PriceCollection::class, $propertyTypeName, true),
            FieldType::CustomFields => $propertyTypeName === 'array' || $propertyTypeName === 'object',
        };
        if (!$valid) {
            throw new EntitySchemaException(\sprintf(
                'Field %s::$%s uses %s storage, which cannot hydrate PHP type %s.',
                $entityClass,
                $property->getName(),
                $fieldType->value,
                $propertyTypeName,
            ));
        }
    }

    /**
     * @return class-string<\BackedEnum>|null
     */
    private function inferEnumClass(\ReflectionProperty $property): ?string
    {
        $type = $property->getType();
        if (!$type instanceof \ReflectionNamedType) {
            return null;
        }
        $name = $type->getName();

        return enum_exists($name) && is_subclass_of($name, \BackedEnum::class) ? $name : null;
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    private function compileTranslation(string $definitionClass, TranslationEntity $attribute): EntitySchema
    {
        if (!is_a($definitionClass, JetpackTranslationDefinition::class, true)) {
            throw new EntitySchemaException(\sprintf('Jetpack translation definition "%s" must extend %s.', $definitionClass, JetpackTranslationDefinition::class));
        }
        if (!\is_subclass_of($attribute->parent, JetpackDefinition::class)) {
            throw new EntitySchemaException('Jetpack translation definitions need a Jetpack parent definition.');
        }
        if (!\is_subclass_of($attribute->entity, Entity::class) || !is_a($attribute->collection, EntityCollection::class, true)) {
            throw new EntitySchemaException(\sprintf('Jetpack translation definition "%s" has invalid entity or collection metadata.', $definitionClass));
        }
        $parent = (new $attribute->parent())->getJetpackSchema();
        if ($parent->translationDefinition !== $definitionClass) {
            throw new EntitySchemaException(\sprintf('Translation definition "%s" is not configured on its parent "%s".', $definitionClass, $attribute->parent));
        }
        $fields = [];
        foreach ($parent->fields as $field) {
            if (!$field->translated) {
                continue;
            }
            $fields[] = new FieldSchema(
                propertyName: $field->propertyName,
                storageName: $field->storageName,
                type: $field->type,
                nullable: $field->nullable,
                required: $field->required,
                primaryKey: false,
                api: $field->api,
                length: $field->length,
                writeProtected: $field->writeProtected,
                computed: $field->computed,
                inherited: $field->inherited,
                inheritanceForeignKey: $field->inheritanceForeignKey,
                searchRanking: $field->searchRanking,
                tokenize: $field->tokenize,
                allowHtml: $field->allowHtml,
                allowEmptyString: $field->allowEmptyString,
                hasDefault: $field->hasDefault,
                default: $field->default,
                renamedFrom: $field->renamedFrom,
                enumClass: $field->enumClass,
                fieldFactory: $field->fieldFactory,
            );
        }

        $parentProperty = $this->camelCase($parent->entityName) . 'Id';
        $fields[] = new FieldSchema(
            propertyName: $parentProperty,
            storageName: $parent->entityName . '_id',
            type: FieldType::Uuid,
            nullable: false,
            required: true,
            primaryKey: true,
            api: ApiScope::All,
            referenceDefinition: $attribute->parent,
            onDelete: OnDelete::Cascade,
            defaultField: true,
        );
        $fields[] = new FieldSchema(
            propertyName: 'languageId',
            storageName: 'language_id',
            type: FieldType::Uuid,
            nullable: false,
            required: true,
            primaryKey: true,
            api: ApiScope::All,
            referenceDefinition: LanguageDefinition::class,
            onDelete: OnDelete::Cascade,
            defaultField: true,
        );
        if ($this->isVersionAware($parent)) {
            $fields[] = new FieldSchema(
                propertyName: $this->camelCase($parent->entityName) . 'VersionId',
                storageName: $parent->entityName . '_version_id',
                type: FieldType::ReferenceVersion,
                nullable: false,
                required: true,
                primaryKey: true,
                api: ApiScope::None,
                referenceDefinition: $attribute->parent,
                referenceVersionFor: $parentProperty,
                defaultField: true,
            );
        }
        $fields[] = $this->timestampField('createdAt', 'created_at', true);
        $fields[] = $this->timestampField('updatedAt', 'updated_at');
        $name = $parent->entityName . '_translation';
        $this->validatePhysicalFields($name, $fields);

        return new EntitySchema(
            definitionClass: $definitionClass,
            entityClass: $attribute->entity,
            collectionClass: $attribute->collection,
            entityName: $name,
            fields: $fields,
            associations: [],
            indexes: [],
            foreignKeys: $this->compileForeignKeys($name, $fields),
            parentDefinition: $attribute->parent,
        );
    }

    /**
     * @param \ReflectionClass<EntityDefinition> $definition
     * @param class-string<EntityDefinition> $definitionClass
     */
    private function compileMapping(\ReflectionClass $definition, string $definitionClass, MappingEntity $attribute): EntitySchema
    {
        if (!$definition->isSubclassOf(JetpackMappingDefinition::class)) {
            throw new EntitySchemaException(\sprintf('Jetpack mapping definition "%s" must extend %s.', $definitionClass, JetpackMappingDefinition::class));
        }
        if ($attribute->name === '') {
            throw new EntitySchemaException('A Jetpack mapping entity needs a non-empty name.');
        }
        if ($attribute->renamedFrom !== null) {
            $this->assertSqlIdentifier($attribute->renamedFrom, 'renamed table');
        }
        $this->assertSqlIdentifier($attribute->engine, 'storage engine');
        $this->assertSqlIdentifier($attribute->charset, 'charset');
        $this->assertSqlIdentifier($attribute->collation, 'collation');
        $this->assertDefinitionClass($attribute->source, $definitionClass);
        $this->assertDefinitionClass($attribute->target, $definitionClass);
        if ($attribute->sourceOnDelete === OnDelete::SetNull || $attribute->targetOnDelete === OnDelete::SetNull) {
            throw new EntitySchemaException(\sprintf('Jetpack mapping entity "%s" cannot use SET NULL on non-nullable primary-key columns.', $attribute->name));
        }
        $fields = [
            new FieldSchema(
                propertyName: $this->camelCase($attribute->sourceColumn),
                storageName: $attribute->sourceColumn,
                type: FieldType::Uuid,
                nullable: false,
                required: true,
                primaryKey: true,
                api: ApiScope::None,
                referenceDefinition: $attribute->source,
                referenceField: $attribute->sourceReferenceField,
                onDelete: $attribute->sourceOnDelete,
            ),
            new FieldSchema(
                propertyName: $this->camelCase($attribute->targetColumn),
                storageName: $attribute->targetColumn,
                type: FieldType::Uuid,
                nullable: false,
                required: true,
                primaryKey: true,
                api: ApiScope::None,
                referenceDefinition: $attribute->target,
                referenceField: $attribute->targetReferenceField,
                onDelete: $attribute->targetOnDelete,
            ),
        ];
        $sourceProperty = $this->camelCase($attribute->sourceColumn);
        $targetProperty = $this->camelCase($attribute->targetColumn);
        if ($attribute->sourceVersionColumn !== null) {
            $fields[] = new FieldSchema(
                propertyName: $this->camelCase($attribute->sourceVersionColumn),
                storageName: $attribute->sourceVersionColumn,
                type: FieldType::ReferenceVersion,
                nullable: false,
                required: true,
                primaryKey: true,
                api: ApiScope::None,
                referenceDefinition: $attribute->source,
                referenceVersionFor: $sourceProperty,
            );
        }
        if ($attribute->targetVersionColumn !== null) {
            $fields[] = new FieldSchema(
                propertyName: $this->camelCase($attribute->targetVersionColumn),
                storageName: $attribute->targetVersionColumn,
                type: FieldType::ReferenceVersion,
                nullable: false,
                required: true,
                primaryKey: true,
                api: ApiScope::None,
                referenceDefinition: $attribute->target,
                referenceVersionFor: $targetProperty,
            );
        }
        $this->validatePhysicalFields($attribute->name, $fields);

        return new EntitySchema(
            definitionClass: $definitionClass,
            entityClass: ArrayEntity::class,
            collectionClass: EntityCollection::class,
            entityName: $attribute->name,
            fields: $fields,
            associations: [],
            indexes: $this->compileIndexes($definition, $attribute->name, $fields),
            foreignKeys: $this->compileForeignKeys($attribute->name, $fields),
            renamedFrom: $attribute->renamedFrom,
            engine: $attribute->engine,
            charset: $attribute->charset,
            collation: $attribute->collation,
        );
    }

    private function timestampField(string $property, string $column, bool $required = false): FieldSchema
    {
        return new FieldSchema(
            propertyName: $property,
            storageName: $column,
            type: FieldType::DateTime,
            nullable: !$required,
            required: $required,
            primaryKey: false,
            api: ApiScope::All,
            defaultField: true,
        );
    }

    private function isVersionAware(EntitySchema $schema): bool
    {
        foreach ($schema->fields as $field) {
            if ($field->type === FieldType::Version) {
                return true;
            }
        }

        return false;
    }

    private function camelCase(string $name): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
    }

    private static function snakeCase(string $name): string
    {
        $normalized = preg_replace('/(?<!^)[A-Z]/', '_$0', $name);

        return strtolower($normalized ?? $name);
    }

    private function assertDefinitionClass(string $definitionClass, string $location): void
    {
        if (!is_a($definitionClass, EntityDefinition::class, true)) {
            throw new EntitySchemaException(\sprintf('Jetpack declaration "%s" references invalid definition "%s".', $location, $definitionClass));
        }
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     * @param array<string, FieldSchema> $properties
     */
    private function validateManyToMany(string $definitionClass, string $entityName, AssociationSchema $association, array $properties): void
    {
        if (!isset($properties[$association->localField])) {
            throw EntitySchemaException::associationLocalFieldMissing($entityName, $association->propertyName, $association->localField);
        }
        $mappingDefinition = $association->mappingDefinition;
        if ($mappingDefinition === null || !is_a($mappingDefinition, JetpackDefinition::class, true)) {
            return;
        }

        /** @var JetpackDefinition $mapping */
        $mapping = new $mappingDefinition();
        $mappingSchema = $mapping->getJetpackSchema();
        $localColumn = $association->mappingLocalColumn;
        $referenceColumn = $association->mappingReferenceColumn;
        $local = null;
        $reference = null;
        $localVersion = null;
        $referenceVersion = null;
        foreach ($mappingSchema->fields as $field) {
            if ($field->storageName === $localColumn) {
                $local = $field;
            }
            if ($field->storageName === $referenceColumn) {
                $reference = $field;
            }
        }
        foreach ($mappingSchema->fields as $field) {
            if ($field->type !== FieldType::ReferenceVersion) {
                continue;
            }
            if ($local instanceof FieldSchema
                && $field->referenceDefinition === $definitionClass
                && $field->referenceVersionFor === $local->propertyName
            ) {
                $localVersion = $field;
            }
            if ($reference instanceof FieldSchema
                && $field->referenceDefinition === $association->targetDefinition
                && $field->referenceVersionFor === $reference->propertyName
            ) {
                $referenceVersion = $field;
            }
        }
        if (!$local instanceof FieldSchema
            || !$reference instanceof FieldSchema
            || $local->referenceDefinition !== $definitionClass
            || $reference->referenceDefinition !== $association->targetDefinition
            || $local->referenceField !== $association->localField
            || $reference->referenceField !== $association->referenceField
            || $local->onDelete !== $association->onDelete
        ) {
            throw new EntitySchemaException(\sprintf('Many-to-many association "%s.%s" does not match its Jetpack mapping columns.', $entityName, $association->propertyName));
        }
        $versioned = false;
        foreach ($properties as $field) {
            $versioned = $versioned || $field->type === FieldType::Version;
        }
        if ($versioned && !$localVersion instanceof FieldSchema) {
            throw new EntitySchemaException(\sprintf('Versioned many-to-many association "%s.%s" needs a source reference-version column.', $entityName, $association->propertyName));
        }
        if ($localVersion instanceof FieldSchema && $localVersion->storageName !== $entityName . '_version_id') {
            throw new EntitySchemaException(\sprintf('Many-to-many association "%s.%s" must name its source version column "%s_version_id".', $entityName, $association->propertyName, $entityName));
        }
        if (($localVersion instanceof FieldSchema || $referenceVersion instanceof FieldSchema) && $association->onDelete !== OnDelete::Cascade) {
            throw new EntitySchemaException(\sprintf('Version-aware many-to-many association "%s.%s" must use CASCADE so Shopware applies version joins.', $entityName, $association->propertyName));
        }
    }

    /**
     * @param list<FieldSchema> $fields
     */
    private function validatePhysicalFields(string $entityName, array $fields): void
    {
        $this->assertSqlIdentifier($entityName, 'table');
        $properties = [];
        $columns = [];
        $hasPrimaryKey = false;
        foreach ($fields as $field) {
            if ($field->primaryKey && ($field->nullable || $field->runtime || $field->translated)) {
                throw new EntitySchemaException(\sprintf('Primary-key field "%s.%s" must be persistent and non-nullable.', $entityName, $field->propertyName));
            }
            if ($field->length !== null && $field->length < 1) {
                throw new EntitySchemaException(\sprintf('Field "%s.%s" has an invalid length.', $entityName, $field->propertyName));
            }
            if ($field->enumClass !== null && $field->type !== FieldType::Enum) {
                throw new EntitySchemaException(\sprintf('Field "%s.%s" configures an enum class but is not an enum field.', $entityName, $field->propertyName));
            }
            if (!$field->hasDefault && $field->default !== null) {
                throw new EntitySchemaException(\sprintf('Field "%s.%s" sets a database default without hasDefault: true.', $entityName, $field->propertyName));
            }
            if (!$field->nullable && $field->hasDefault && $field->default === null) {
                throw new EntitySchemaException(\sprintf('Non-nullable field "%s.%s" cannot use NULL as its database default.', $entityName, $field->propertyName));
            }
            if ($field->hasDefault && $field->default !== null) {
                $this->validateDefault($entityName, $field);
            }
            if (isset($properties[$field->propertyName])) {
                throw new EntitySchemaException(\sprintf('Entity "%s" contains duplicate field property "%s".', $entityName, $field->propertyName));
            }
            if (!$field->runtime && !$field->translated && isset($columns[$field->storageName])) {
                throw EntitySchemaException::duplicateStorageName($entityName, $field->storageName);
            }
            if (!$field->runtime && !$field->translated) {
                $this->assertSqlIdentifier($field->storageName, 'column');
            }
            if ($field->renamedFrom !== null) {
                $this->assertSqlIdentifier($field->renamedFrom, 'renamed column');
            }
            $properties[$field->propertyName] = true;
            if (!$field->runtime && !$field->translated) {
                $columns[$field->storageName] = true;
            }
            $hasPrimaryKey = $hasPrimaryKey || $field->primaryKey;
        }
        if (!$hasPrimaryKey) {
            throw EntitySchemaException::primaryKeyMissing($entityName);
        }
    }

    private function assertSqlIdentifier(string $identifier, string $kind): void
    {
        if (\strlen($identifier) > 64 || preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $identifier) !== 1) {
            throw new EntitySchemaException(\sprintf('Invalid MySQL %s identifier "%s".', $kind, $identifier));
        }
    }

    private function validateDefault(string $entityName, FieldSchema $field): void
    {
        $valid = match ($field->type) {
            FieldType::String, FieldType::Text, FieldType::Email, FieldType::Date, FieldType::DateTime => \is_string($field->default),
            FieldType::Int => \is_int($field->default),
            FieldType::Float => \is_int($field->default) || \is_float($field->default),
            FieldType::Bool => \is_bool($field->default),
            FieldType::Enum => $this->validEnumDefault($field),
            FieldType::Custom => true,
            default => false,
        };
        if (!$valid) {
            throw new EntitySchemaException(\sprintf('Field "%s.%s" has an unsupported or invalid database default.', $entityName, $field->propertyName));
        }
    }

    private function validEnumDefault(FieldSchema $field): bool
    {
        $enumClass = $field->enumClass;
        if ($enumClass === null || (!\is_string($field->default) && !\is_int($field->default))) {
            return false;
        }

        try {
            return $enumClass::tryFrom($field->default) !== null;
        } catch (\TypeError) {
            return false;
        }
    }
}
