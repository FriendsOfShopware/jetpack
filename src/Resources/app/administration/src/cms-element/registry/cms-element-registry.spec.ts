import { CmsElementRegistry } from './cms-element-registry';
import type { CmsElementDefinition } from '../types';

function definition(overrides: Partial<CmsElementDefinition> = {}): CmsElementDefinition {
    return {
        apiVersion: 1,
        name: 'acme-recipe',
        labelSnippet: 'acme-recipe.cms.label',
        allowedPageTypes: ['landingpage'],
        fields: [
            {
                name: 'recipe',
                type: 'entity-select',
                entity: 'acme_recipe',
                labelProperty: 'name',
                labelSnippet: 'acme-recipe.cms.recipe',
                required: true,
                defaultValue: null,
            },
            {
                name: 'layout',
                type: 'select',
                labelSnippet: 'acme-recipe.cms.layout',
                defaultValue: 'card',
                options: [
                    {
                        value: 'card',
                        labelSnippet: 'acme-recipe.cms.layouts.card',
                    },
                ],
            },
        ],
        ...overrides,
    };
}

describe('CmsElementRegistry', () => {
    it('registers one definition and exposes its immutable snapshot', () => {
        const registrar = jest.fn();
        const registry = new CmsElementRegistry(registrar);
        const consumerDefinition = definition();

        expect(registry.register(consumerDefinition)).toEqual({ name: 'acme-recipe' });
        expect(registrar).toHaveBeenCalledTimes(1);
        expect(registry.get('acme-recipe')).toEqual(expect.objectContaining({ name: 'acme-recipe' }));
        expect(registry.all()).toHaveLength(1);

        consumerDefinition.fields[0].name = 'changed';
        consumerDefinition.allowedPageTypes?.push('product_detail');

        const registered = registry.get('acme-recipe');
        expect(registered?.fields[0].name).toBe('recipe');
        expect(registered?.allowedPageTypes).toEqual(['landingpage']);
        expect(Object.isFrozen(registered?.fields)).toBe(true);
        expect(Object.isFrozen(registry.all())).toBe(true);
    });

    it('rejects duplicate registrations before calling Shopware again', () => {
        const registrar = jest.fn();
        const registry = new CmsElementRegistry(registrar);
        registry.register(definition());

        expect(() => registry.register(definition())).toThrow('registration already exists');
        expect(registrar).toHaveBeenCalledTimes(1);
    });

    it.each([
        [
            'wrong API version',
            definition({ apiVersion: 2 as 1 }),
            'apiVersion must be 1',
        ],
        [
            'unsafe element name',
            definition({ name: 'recipe' }),
            'must contain at least two lower-case hyphen-separated segments',
        ],
        [
            'empty field collection',
            definition({ fields: [] }),
            'fields must contain a field',
        ],
        [
            'duplicate field names',
            definition({
                fields: [
                    {
                        name: 'title',
                        type: 'text',
                        labelSnippet: 'acme.title',
                    },
                    {
                        name: 'title',
                        type: 'text',
                        labelSnippet: 'acme.titleAgain',
                    },
                ],
            }),
            'fields contains duplicate name "title"',
        ],
        [
            'unsafe field name',
            definition({
                fields: [
                    {
                        name: 'constructor',
                        type: 'text',
                        labelSnippet: 'acme.title',
                    },
                ],
            }),
            'fields[0].name must be a safe lower-camel-case key',
        ],
        [
            'missing entity',
            definition({
                fields: [
                    {
                        name: 'recipe',
                        type: 'entity-select',
                        entity: '',
                        labelSnippet: 'acme.recipe',
                    },
                ],
            }),
            'fields[0].entity must not be empty',
        ],
        [
            'duplicate option',
            definition({
                fields: [
                    {
                        name: 'layout',
                        type: 'select',
                        labelSnippet: 'acme.layout',
                        options: [
                            { value: 'card', labelSnippet: 'acme.card' },
                            { value: 'card', labelSnippet: 'acme.cardAgain' },
                        ],
                    },
                ],
            }),
            'fields[0].options contains duplicate value "card"',
        ],
        [
            'invalid numeric default',
            definition({
                fields: [
                    {
                        name: 'limit',
                        type: 'number',
                        labelSnippet: 'acme.limit',
                        defaultValue: Number.POSITIVE_INFINITY,
                    },
                ],
            }),
            'fields[0].defaultValue must be a finite number',
        ],
        [
            'duplicate page type',
            definition({
                allowedPageTypes: [
                    'landingpage',
                    'landingpage',
                ],
            }),
            'allowedPageTypes contains duplicate value "landingpage"',
        ],
    ])('rejects %s', (_case, invalid, message) => {
        const registry = new CmsElementRegistry(jest.fn());

        expect(() => registry.register(invalid)).toThrow(message);
    });

    it('keeps the field discriminators in consumer definitions', () => {
        const typed = definition() satisfies CmsElementDefinition;

        expect(typed.fields[0].type).toBe('entity-select');
    });
});
