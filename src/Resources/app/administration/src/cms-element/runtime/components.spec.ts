import { shallowMount } from '@vue/test-utils';
import CmsElementComponent from './component';
import CmsElementConfig from './config';
import type { FroshJetpackGlobal } from '../../administration-crud/types';
import type { CmsElementDefinition, CmsElementField } from '../types';

const definition: CmsElementDefinition = {
    apiVersion: 1,
    name: 'acme-recipe',
    labelSnippet: 'acme-recipe.cms.label',
    fields: [
        {
            name: 'recipe',
            type: 'entity-select',
            entity: 'acme_recipe',
            labelProperty: 'title',
            labelSnippet: 'acme-recipe.cms.recipe',
            required: true,
        },
        {
            name: 'layout',
            type: 'select',
            labelSnippet: 'acme-recipe.cms.layout',
            options: [
                {
                    value: 'card',
                    labelSnippet: 'acme-recipe.cms.layouts.card',
                },
            ],
        },
        {
            name: 'featured',
            type: 'switch',
            labelSnippet: 'acme-recipe.cms.featured',
        },
    ],
};

function installTestGlobal(): void {
    globalThis.FroshJetpack = {
        apiVersion: 1,
        runtimeVersion: 'test',
        Admin: {
            Cms: {
                register: jest.fn(),
                get: (name: string) => (name === definition.name ? definition : undefined),
                all: () => [definition],
            },
            Crud: {
                register: jest.fn(),
                extend: jest.fn(),
                get: () => undefined,
                all: () => [],
            },
        },
    } as FroshJetpackGlobal;
}

describe('declarative CMS runtime components', () => {
    beforeEach(() => {
        installTestGlobal();
    });

    it('maps field definitions to Jetpack controls and updates scalar values', async () => {
        const element = {
            type: 'acme-recipe',
            config: {
                recipe: { source: 'static', value: null },
                layout: { source: 'static', value: 'card' },
                featured: { source: 'static', value: false },
            },
            data: { recipe: null },
        };
        const wrapper = shallowMount(CmsElementConfig, {
            props: {
                element: element as never,
                elementData: { name: 'acme-recipe' },
            },
            global: {
                mocks: {
                    $t: (key: string) => `translated:${key}`,
                },
                provide: {
                    cmsService: {},
                    repositoryFactory: {
                        create: () => ({ get: jest.fn() }),
                    },
                },
                stubs: {
                    'jetpack-stack': true,
                    'sw-cms-inherit-wrapper': true,
                },
            },
        });
        const component = wrapper.vm as unknown as {
            componentFor: (field: CmsElementField) => string;
            fieldProps: (field: CmsElementField, inherited: boolean) => Record<string, unknown>;
            updateField: (field: CmsElementField, value: string | boolean) => Promise<void>;
        };

        expect(component.componentFor(definition.fields[0])).toBe('jetpack-entity-select');
        expect(component.componentFor(definition.fields[1])).toBe('jetpack-select');
        expect(component.componentFor(definition.fields[2])).toBe('jetpack-switch');
        expect(component.fieldProps(definition.fields[1], true)).toEqual(
            expect.objectContaining({
                disabled: true,
                options: [
                    expect.objectContaining({
                        value: 'card',
                        label: 'translated:acme-recipe.cms.layouts.card',
                    }),
                ],
            }),
        );

        await component.updateField(definition.fields[2], true);
        expect(element.config.featured.value).toBe(true);
        expect(wrapper.emitted('element-update')?.at(-1)).toEqual([element]);
    });

    it('hydrates a selected entity for an immediate canvas update', async () => {
        const recipe = {
            id: 'recipe-1',
            title: 'Summer salad',
        };
        const repository = {
            get: jest.fn().mockResolvedValue(recipe),
        };
        const element = {
            type: 'acme-recipe',
            config: {
                recipe: { source: 'static', value: null },
                layout: { source: 'static', value: 'card' },
                featured: { source: 'static', value: false },
            },
            data: { recipe: null },
        };
        const wrapper = shallowMount(CmsElementConfig, {
            props: {
                element: element as never,
                elementData: { name: 'acme-recipe' },
            },
            global: {
                mocks: {
                    $t: (key: string) => key,
                },
                provide: {
                    cmsService: {},
                    repositoryFactory: {
                        create: () => repository,
                    },
                },
                stubs: {
                    'jetpack-stack': true,
                    'sw-cms-inherit-wrapper': true,
                },
            },
        });
        const component = wrapper.vm as unknown as {
            updateField: (field: CmsElementField, value: string) => Promise<void>;
        };

        await component.updateField(definition.fields[0], recipe.id);

        expect(repository.get).toHaveBeenCalledWith(recipe.id, Shopware.Context.api, expect.anything());
        expect(element.data.recipe).toBe(recipe);
        expect(wrapper.emitted('element-update')).toHaveLength(2);
    });

    it('renders the translated label property from enriched slot data', () => {
        const element = {
            type: 'acme-recipe',
            config: {
                recipe: { source: 'static', value: 'recipe-1' },
                layout: { source: 'static', value: 'card' },
                featured: { source: 'static', value: true },
            },
            data: {
                recipe: {
                    id: 'recipe-1',
                    title: 'Fallback title',
                    translated: { title: 'Translated title' },
                },
            },
        };
        const wrapper = shallowMount(CmsElementComponent, {
            props: {
                element: element as never,
                elementData: { name: 'acme-recipe' },
            },
            global: {
                mocks: {
                    $t: (key: string) => key,
                },
                provide: {
                    cmsService: {},
                },
                stubs: {
                    'jetpack-card': true,
                    'jetpack-stack': true,
                },
            },
        });
        const component = wrapper.vm as unknown as {
            displayValue: (field: CmsElementField) => string;
        };

        expect(component.displayValue(definition.fields[0])).toBe('Translated title');
        expect(component.displayValue(definition.fields[2])).toBe('frosh-jetpack-cms.values.yes');
    });
});
