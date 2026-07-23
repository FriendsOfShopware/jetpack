<?php declare(strict_types=1);

namespace Frosh\Jetpack\Entity;

use Frosh\Jetpack\Attribute\ApiScope;
use Frosh\Jetpack\Attribute\FieldType;
use Frosh\Jetpack\Attribute\OnDelete;
use Frosh\Jetpack\Entity\Field\BackedEnumField;
use Frosh\Jetpack\Entity\Field\FieldFactory;
use Frosh\Jetpack\Entity\Schema\AssociationSchema;
use Frosh\Jetpack\Entity\Schema\AssociationType;
use Frosh\Jetpack\Entity\Schema\EntitySchema;
use Frosh\Jetpack\Entity\Schema\FieldSchema;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class DalFieldCompiler
{
    public function compile(EntitySchema $schema): FieldCollection
    {
        $fields = [];
        foreach ($schema->fields as $fieldSchema) {
            if ($fieldSchema->defaultField) {
                continue;
            }
            $fields[] = $this->compileField($schema, $fieldSchema);
        }
        foreach ($schema->associations as $association) {
            $fields[] = $this->compileAssociation($schema, $association);
        }
        if ($schema->translationDefinition !== null) {
            $translationField = new TranslationsAssociationField(
                $schema->translationDefinition,
                $schema->entityName . '_id',
            );
            $this->applyFlags($translationField, new Required());
            $fields[] = $translationField;
        }

        return new FieldCollection($fields);
    }

    private function compileField(EntitySchema $entity, FieldSchema $schema): Field
    {
        if ($schema->translated) {
            return $this->addFlags(new TranslatedField($schema->propertyName), $schema);
        }

        $field = match ($schema->type) {
            FieldType::Uuid => new IdField($schema->storageName, $schema->propertyName),
            FieldType::String => new StringField($schema->storageName, $schema->propertyName, $schema->length ?? 255),
            FieldType::Text => new LongTextField($schema->storageName, $schema->propertyName),
            FieldType::Int => new IntField($schema->storageName, $schema->propertyName),
            FieldType::Float => new FloatField($schema->storageName, $schema->propertyName),
            FieldType::Bool => new BoolField($schema->storageName, $schema->propertyName),
            FieldType::DateTime => new DateTimeField($schema->storageName, $schema->propertyName),
            FieldType::Date => new DateField($schema->storageName, $schema->propertyName),
            FieldType::Json => new JsonField($schema->storageName, $schema->propertyName),
            FieldType::Blob => new BlobField($schema->storageName, $schema->propertyName),
            FieldType::Email => new EmailField($schema->storageName, $schema->propertyName, $schema->length ?? 255),
            FieldType::Price => new PriceField($schema->storageName, $schema->propertyName),
            FieldType::CustomFields => new CustomFields($schema->storageName, $schema->propertyName),
            FieldType::Enum => new BackedEnumField($schema->storageName, $schema->propertyName, $this->enumClass($schema)),
            FieldType::Version => new VersionField(),
            FieldType::ReferenceVersion => new ReferenceVersionField(
                $schema->referenceDefinition ?? throw new EntitySchemaException('Reference-version field needs a target definition.'),
                $schema->storageName,
            ),
            FieldType::Custom => $this->customField($schema),
        };

        if ($schema->referenceDefinition !== null && $schema->type !== FieldType::ReferenceVersion) {
            $referenceDefinition = $schema->referenceDefinition;
            $field = $entity->inheritanceAware
                && $schema->propertyName === 'parentId'
                && $schema->storageName === 'parent_id'
                && $referenceDefinition === $entity->definitionClass
                ? new ParentFkField($referenceDefinition)
                : new FkField($schema->storageName, $schema->propertyName, $referenceDefinition, $schema->referenceField);
        }

        return $this->addFlags($field, $schema);
    }

    private function addFlags(Field $field, FieldSchema $schema): Field
    {
        $this->applyApiScope($field, $schema->api);
        if ($schema->required) {
            $this->applyFlags($field, new Required());
        }
        if ($schema->primaryKey) {
            $this->applyFlags($field, new PrimaryKey());
        }
        if ($schema->writeProtected) {
            $this->applyFlags($field, new WriteProtected());
        }
        if ($schema->runtime) {
            $this->applyFlags($field, new Runtime());
        }
        if ($schema->computed) {
            $this->applyFlags($field, new Computed());
        }
        if ($schema->inherited) {
            $this->applyFlags($field, new Inherited($schema->inheritanceForeignKey));
        }
        if ($schema->searchRanking !== null) {
            $this->applyFlags($field, new SearchRanking($schema->searchRanking, $schema->tokenize));
        }
        if ($schema->allowHtml) {
            $this->applyFlags($field, new AllowHtml());
        }
        if ($schema->allowEmptyString) {
            $this->applyFlags($field, new AllowEmptyString());
        }

        return $field;
    }

    private function compileAssociation(EntitySchema $entity, AssociationSchema $schema): Field
    {
        $localField = null;
        if ($schema->type === AssociationType::ManyToOne || $schema->type === AssociationType::OneToOne) {
            $localField = $entity->field($schema->localField);
        }
        $localStorageName = $localField?->storageName;
        $field = match ($schema->type) {
            AssociationType::ManyToOne => $entity->inheritanceAware && $schema->propertyName === 'parent' && $schema->targetDefinition === $entity->definitionClass
                ? new ParentAssociationField($schema->targetDefinition, $schema->referenceField)
                : new ManyToOneAssociationField($schema->propertyName, $localStorageName ?? throw new \LogicException('Many-to-one association needs a local field.'), $schema->targetDefinition, $schema->referenceField, $schema->autoload),
            AssociationType::OneToOne => new OneToOneAssociationField($schema->propertyName, $localStorageName ?? throw new \LogicException('One-to-one association needs a local field.'), $schema->referenceField, $schema->targetDefinition, $schema->autoload),
            AssociationType::OneToMany => $entity->inheritanceAware && $schema->targetDefinition === $entity->definitionClass && $schema->referenceField === 'parentId'
                ? new ChildrenAssociationField($schema->targetDefinition, $schema->propertyName)
                : new OneToManyAssociationField($schema->propertyName, $schema->targetDefinition, $schema->referenceField, $schema->localField),
            AssociationType::ManyToMany => new ManyToManyAssociationField(
                $schema->propertyName,
                $schema->targetDefinition,
                $schema->mappingDefinition ?? throw new EntitySchemaException('Many-to-many association needs a mapping definition.'),
                $schema->mappingLocalColumn ?? throw new EntitySchemaException('Many-to-many association needs a local mapping column.'),
                $schema->mappingReferenceColumn ?? throw new EntitySchemaException('Many-to-many association needs a reference mapping column.'),
                $schema->localField,
                $schema->referenceField,
            ),
        };

        if ($localField !== null) {
            $this->applyFlags($field, $this->deleteFlag($localField->onDelete));
        } elseif ($schema->onDelete !== null) {
            $this->applyFlags($field, $this->deleteFlag($schema->onDelete));
        }
        $this->applyApiScope($field, $schema->api);
        if ($schema->inherited) {
            $this->applyFlags($field, new Inherited($schema->inheritanceForeignKey));
        }

        return $field;
    }

    private function deleteFlag(OnDelete $onDelete): Flag
    {
        return match ($onDelete) {
            OnDelete::Cascade => new CascadeDelete(),
            OnDelete::Restrict, OnDelete::NoAction => new RestrictDelete(),
            OnDelete::SetNull => new SetNullOnDelete(),
        };
    }

    /**
     * @return class-string<\BackedEnum>
     */
    private function enumClass(FieldSchema $schema): string
    {
        $class = $schema->enumClass;
        if ($class === null || !enum_exists($class) || !is_subclass_of($class, \BackedEnum::class)) {
            throw new EntitySchemaException(\sprintf('Enum field "%s" needs a backed enum class.', $schema->propertyName));
        }

        return $class;
    }

    private function customField(FieldSchema $schema): Field
    {
        $factoryClass = $schema->fieldFactory;
        if ($factoryClass === null || !is_a($factoryClass, FieldFactory::class, true)) {
            throw new EntitySchemaException(\sprintf('Custom field "%s" has no valid field factory.', $schema->propertyName));
        }

        $field = (new $factoryClass())->create($schema);
        if ($field->getPropertyName() !== $schema->propertyName
            || (!$schema->runtime && (!$field instanceof StorageAware || $field->getStorageName() !== $schema->storageName))
        ) {
            throw new EntitySchemaException(\sprintf('Field factory "%s" returned a field with incompatible property or storage names.', $factoryClass));
        }

        return $field;
    }

    private function applyFlags(Field $field, Flag ...$flags): void
    {
        /** @phpstan-ignore method.deprecated (Jetpack supports Shopware 6.6 and 6.7, where addFlags has the compatible signature.) */
        $field->addFlags(...$flags);
    }

    private function applyApiScope(Field $field, ApiScope $scope): void
    {
        /** @phpstan-ignore method.deprecated (Jetpack supports Shopware 6.6 and 6.7, where removeFlag has the compatible signature.) */
        $field->removeFlag(ApiAware::class);
        if ($scope === ApiScope::None) {
            return;
        }

        $apiAware = match ($scope) {
            ApiScope::Admin => new ApiAware(AdminApiSource::class),
            ApiScope::Store => new ApiAware(SalesChannelApiSource::class),
            ApiScope::All => new ApiAware(),
        };
        $this->applyFlags($field, $apiAware);
    }
}
