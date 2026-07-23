<?php declare(strict_types=1);

namespace Frosh\Jetpack\CustomField;

use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 *
 * @phpstan-import-type FieldDefinition from CustomFieldDefinition
 * @phpstan-import-type SetDefinition from CustomFieldDefinition
 */
final class CustomFieldPayloadCompiler
{
    /**
     * @param SetDefinition $set
     * @param array<string, string> $existingRelations
     * @param array<string, array{id: string, type: string}> $existingFields
     *
     * @return array{setId: string, payload: array<string, mixed>, obsoleteRelations: list<string>, obsoleteFields: list<string>}
     */
    public function compile(
        string $bundleName,
        array $set,
        ?string $existingSetId,
        array $existingRelations,
        array $existingFields,
        bool $supportsIncludeInSearch,
    ): array {
        $setId = $existingSetId ?? $this->setId($bundleName, $set['name']);
        $relations = [];
        foreach ($set['relations'] as $entityName) {
            $relations[] = [
                'id' => $existingRelations[$entityName] ?? $this->id($bundleName, 'relation', $set['name'], $entityName),
                'entityName' => $entityName,
            ];
            unset($existingRelations[$entityName]);
        }

        $fields = [];
        foreach ($set['fields'] as $field) {
            $existingField = $existingFields[$field['name']] ?? null;
            $storageType = $this->storageType($field['type']);
            if ($existingField !== null && $existingField['type'] !== $storageType) {
                throw CustomFieldException::immutableFieldType($field['name'], $existingField['type'], $storageType);
            }

            $payload = [
                'id' => $existingField['id'] ?? $this->id($bundleName, 'field', $field['name']),
                'active' => $field['active'],
                'allowCustomerWrite' => $field['allowCustomerWrite'],
                'allowCartExpose' => $field['allowCartExpose'],
                'config' => $this->fieldConfig($field),
            ];
            if ($supportsIncludeInSearch) {
                $payload['includeInSearch'] = $field['includeInSearch'];
            }
            if ($existingField === null) {
                $payload['name'] = $field['name'];
                $payload['type'] = $storageType;
            }

            $fields[] = $payload;
            unset($existingFields[$field['name']]);
        }

        $payload = [
            'id' => $setId,
            'active' => $set['active'],
            'global' => $set['global'],
            'position' => $set['position'],
            'config' => [
                'label' => $set['label'],
                'translated' => true,
            ],
            'relations' => $relations,
            'customFields' => $fields,
        ];
        if ($existingSetId === null) {
            $payload['name'] = $set['name'];
        }

        return [
            'setId' => $setId,
            'payload' => $payload,
            'obsoleteRelations' => array_values($existingRelations),
            'obsoleteFields' => array_values(array_map(
                static fn (array $field): string => $field['id'],
                $existingFields,
            )),
        ];
    }

    public function setId(string $bundleName, string $setName): string
    {
        return $this->id($bundleName, 'set', $setName);
    }

    /**
     * @param FieldDefinition $field
     *
     * @return array<string, mixed>
     */
    private function fieldConfig(array $field): array
    {
        $config = [
            'label' => $field['label'],
            'helpText' => $field['helpText'],
            'customFieldPosition' => $field['position'],
        ];
        if ($field['required']) {
            $config['validation'] = 'required';
        }

        $typeConfig = match ($field['type']) {
            'int' => $this->numberConfig($field, 'int'),
            'float' => $this->numberConfig($field, 'float'),
            'text' => [
                'type' => 'text',
                'placeholder' => $field['placeholder'],
                'componentName' => 'sw-field',
                'customFieldType' => 'text',
            ],
            'text-area' => [
                'placeholder' => $field['placeholder'],
                'componentName' => 'sw-text-editor',
                'customFieldType' => 'textEditor',
            ],
            'bool' => [
                'type' => 'checkbox',
                'componentName' => 'sw-field',
                'customFieldType' => 'checkbox',
            ],
            'datetime' => [
                'type' => 'date',
                'componentName' => 'sw-field',
                'customFieldType' => 'date',
                'config' => ['time_24hr' => true],
                'dateType' => 'datetime',
            ],
            'single-select', 'multi-select' => $this->selectConfig($field),
            'single-entity-select', 'multi-entity-select' => $this->entitySelectConfig($field),
            'color-picker' => [
                'type' => 'colorpicker',
                'componentName' => 'sw-field',
                'customFieldType' => 'colorpicker',
            ],
            'media-selection' => [
                'componentName' => 'sw-media-field',
                'customFieldType' => 'media',
            ],
            'price' => [
                'type' => 'price',
                'componentName' => 'sw-price-field',
                'customFieldType' => 'price',
            ],
            default => throw new \LogicException(\sprintf('Unsupported custom field type "%s".', $field['type'])),
        };

        return array_merge($config, $typeConfig);
    }

    /**
     * @param FieldDefinition $field
     *
     * @return array<string, mixed>
     */
    private function numberConfig(array $field, string $numberType): array
    {
        $config = [
            'type' => 'number',
            'placeholder' => $field['placeholder'],
            'componentName' => 'sw-field',
            'customFieldType' => 'number',
            'numberType' => $numberType,
        ];
        foreach (['min', 'max', 'step'] as $option) {
            if (isset($field[$option])) {
                $config[$option] = $field[$option];
            }
        }

        return $config;
    }

    /**
     * @param FieldDefinition $field
     *
     * @return array<string, mixed>
     */
    private function selectConfig(array $field): array
    {
        $options = [];
        foreach ($field['options'] ?? [] as $value => $option) {
            $options[] = ['value' => $value, 'label' => $option['label']];
        }

        return [
            'placeholder' => $field['placeholder'],
            'componentName' => $field['type'] === 'single-select' ? 'sw-single-select' : 'sw-multi-select',
            'customFieldType' => 'select',
            'options' => $options,
        ];
    }

    /**
     * @param FieldDefinition $field
     *
     * @return array<string, mixed>
     */
    private function entitySelectConfig(array $field): array
    {
        if (!isset($field['entity'])) {
            throw new \LogicException(\sprintf('Custom field "%s" requires an entity.', $field['name']));
        }

        $config = [
            'entity' => $field['entity'],
            'placeholder' => $field['placeholder'],
            'componentName' => $field['type'] === 'single-entity-select' ? 'sw-entity-single-select' : 'sw-entity-multi-id-select',
            'customFieldType' => 'select',
        ];
        if (isset($field['labelProperty'])) {
            $config['labelProperty'] = $field['labelProperty'];
        }

        return $config;
    }

    private function storageType(string $type): string
    {
        return match ($type) {
            'int' => CustomFieldTypes::INT,
            'float' => CustomFieldTypes::FLOAT,
            'text', 'color-picker', 'media-selection' => CustomFieldTypes::TEXT,
            'text-area' => CustomFieldTypes::HTML,
            'bool' => CustomFieldTypes::BOOL,
            'datetime' => CustomFieldTypes::DATETIME,
            'single-select', 'multi-select' => CustomFieldTypes::SELECT,
            'single-entity-select', 'multi-entity-select' => CustomFieldTypes::ENTITY,
            'price' => CustomFieldTypes::PRICE,
            default => throw new \LogicException(\sprintf('Unsupported custom field type "%s".', $type)),
        };
    }

    private function id(string ...$parts): string
    {
        return Uuid::fromStringToHex('frosh.jetpack.custom-field.' . implode('.', $parts));
    }
}
