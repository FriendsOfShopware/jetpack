import type { CmsElementDefinition, CmsElementField, CmsElementValue } from '../types';

const ELEMENT_NAME = /^[a-z][a-z0-9]*(?:-[a-z0-9]+)+$/;
const FIELD_NAME = /^[a-z][A-Za-z0-9]*$/;
const UNSAFE_FIELD_NAMES = new Set([
    '__proto__',
    'constructor',
    'prototype',
]);
const FIELD_TYPES = new Set([
    'text',
    'textarea',
    'number',
    'switch',
    'select',
    'entity-select',
]);

function fail(name: string, path: string, message: string): never {
    throw new Error(`[FroshJetpack Admin CMS] ${name}: ${path} ${message}`);
}

function validateDefaultValue(name: string, path: string, field: CmsElementField): void {
    if (field.defaultValue === undefined || field.defaultValue === null) {
        return;
    }

    if (
        (field.type === 'text' || field.type === 'textarea' || field.type === 'entity-select') &&
        typeof field.defaultValue !== 'string'
    ) {
        fail(name, `${path}.defaultValue`, `must be a string or null for "${field.type}"`);
    }

    if (field.type === 'number' && typeof field.defaultValue !== 'number') {
        fail(name, `${path}.defaultValue`, 'must be a number or null');
    }

    if (field.type === 'switch' && typeof field.defaultValue !== 'boolean') {
        fail(name, `${path}.defaultValue`, 'must be a boolean');
    }
}

function validateField(name: string, path: string, field: CmsElementField): void {
    if (!FIELD_NAME.test(field.name) || UNSAFE_FIELD_NAMES.has(field.name)) {
        fail(name, `${path}.name`, 'must be a safe lower-camel-case key');
    }

    if (!field.labelSnippet.trim()) {
        fail(name, `${path}.labelSnippet`, 'must not be empty');
    }

    [
        'helpTextSnippet',
        'placeholderSnippet',
    ].forEach((property) => {
        const value = field[property as 'helpTextSnippet' | 'placeholderSnippet'];
        if (value !== undefined && !value.trim()) {
            fail(name, `${path}.${property}`, 'must not be empty');
        }
    });

    if (!FIELD_TYPES.has(field.type)) {
        fail(name, `${path}.type`, `contains unknown renderer "${field.type}"`);
    }

    if (field.type === 'select') {
        if (field.options.length === 0) {
            fail(name, `${path}.options`, 'must contain an option');
        }

        const values = new Set<CmsElementValue>();
        field.options.forEach((option, optionIndex) => {
            if (!option.labelSnippet.trim()) {
                fail(name, `${path}.options[${optionIndex}].labelSnippet`, 'must not be empty');
            }
            if (values.has(option.value)) {
                fail(name, `${path}.options`, `contains duplicate value "${String(option.value)}"`);
            }

            values.add(option.value);
        });
    }

    if (field.type === 'entity-select') {
        if (!field.entity.trim()) {
            fail(name, `${path}.entity`, 'must not be empty');
        }
        if (field.labelProperty !== undefined && !field.labelProperty.trim()) {
            fail(name, `${path}.labelProperty`, 'must not be empty');
        }
        if (field.criteria !== undefined && typeof field.criteria !== 'function') {
            fail(name, `${path}.criteria`, 'must be a function');
        }
    }

    [
        'min',
        'max',
        'step',
    ].forEach((property) => {
        if (field.type !== 'number') {
            return;
        }

        const value = field[property as 'min' | 'max' | 'step'];
        if (value !== undefined && !Number.isFinite(value)) {
            fail(name, `${path}.${property}`, 'must be a finite number');
        }
    });

    validateDefaultValue(name, path, field);

    if (
        field.type === 'number' &&
        field.defaultValue !== undefined &&
        field.defaultValue !== null &&
        !Number.isFinite(field.defaultValue)
    ) {
        fail(name, `${path}.defaultValue`, 'must be a finite number');
    }
}

export function validateCmsElementDefinition(definition: CmsElementDefinition): void {
    const name = definition.name || '<unknown>';

    if (definition.apiVersion !== 1) {
        fail(name, 'apiVersion', 'must be 1');
    }

    if (!ELEMENT_NAME.test(definition.name)) {
        fail(name, 'name', 'must contain at least two lower-case hyphen-separated segments');
    }

    if (!definition.labelSnippet.trim()) {
        fail(name, 'labelSnippet', 'must not be empty');
    }

    if (definition.fields.length === 0) {
        fail(name, 'fields', 'must contain a field');
    }

    const fieldNames = new Set<string>();
    definition.fields.forEach((field, index) => {
        validateField(name, `fields[${index}]`, field);
        if (fieldNames.has(field.name)) {
            fail(name, 'fields', `contains duplicate name "${field.name}"`);
        }

        fieldNames.add(field.name);
    });

    const pageTypes = new Set<string>();
    definition.allowedPageTypes?.forEach((pageType, index) => {
        if (!pageType.trim()) {
            fail(name, `allowedPageTypes[${index}]`, 'must not be empty');
        }
        if (pageTypes.has(pageType)) {
            fail(name, 'allowedPageTypes', `contains duplicate value "${pageType}"`);
        }

        pageTypes.add(pageType);
    });
}
