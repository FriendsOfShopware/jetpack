import { registerCmsElement } from './register-cms-element';
import type { CmsElementDefinition } from './types';

describe('registerCmsElement', () => {
    beforeEach(() => {
        (Shopware.Service as jest.Mock).mockReset();
    });

    it('projects a declarative definition into Shopware CMS configuration', () => {
        const collect = jest.fn();
        const cmsService = {
            getCollectFunction: jest.fn(() => collect),
            registerCmsElement: jest.fn((config: unknown) => Boolean(config)),
        };
        (Shopware.Service as jest.Mock).mockImplementation(() => cmsService);
        const definition: CmsElementDefinition = {
            apiVersion: 1,
            name: 'acme-recipe',
            labelSnippet: 'acme-recipe.cms.label',
            allowedPageTypes: ['landingpage'],
            hidden: false,
            removable: true,
            fields: [
                {
                    name: 'headline',
                    type: 'text',
                    labelSnippet: 'acme-recipe.cms.headline',
                },
                {
                    name: 'recipe',
                    type: 'entity-select',
                    entity: 'acme_recipe',
                    labelSnippet: 'acme-recipe.cms.recipe',
                    required: true,
                    criteria({ criteria }) {
                        criteria.addAssociation('media');
                    },
                },
            ],
        };

        registerCmsElement(definition);

        expect(cmsService.getCollectFunction).toHaveBeenCalledTimes(1);
        type RegisteredConfig = {
            defaultConfig: {
                recipe: {
                    entity: {
                        criteria: {
                            associations: string[];
                        };
                    };
                };
            };
        };
        const registeredConfig = cmsService.registerCmsElement.mock.calls[0][0] as RegisteredConfig;

        expect(cmsService.registerCmsElement).toHaveBeenCalledTimes(1);
        expect(cmsService.registerCmsElement.mock.calls[0][0]).toMatchObject({
            name: 'acme-recipe',
            label: 'acme-recipe.cms.label',
            component: 'frosh-jetpack-cms-element',
            configComponent: 'frosh-jetpack-cms-element-config',
            previewComponent: 'frosh-jetpack-cms-element-preview',
            allowedPageTypes: ['landingpage'],
            hidden: false,
            removable: true,
            collect,
            defaultConfig: {
                headline: {
                    source: 'static',
                    value: '',
                },
                recipe: {
                    source: 'static',
                    value: null,
                    required: true,
                    entity: {
                        name: 'acme_recipe',
                    },
                },
            },
            defaultData: {
                recipe: null,
            },
        });
        expect(registeredConfig.defaultConfig.recipe.entity.criteria.associations).toEqual(['media']);
    });

    it('turns a rejected Shopware registration into a useful error', () => {
        (Shopware.Service as jest.Mock).mockReturnValue({
            getCollectFunction: () => jest.fn(),
            registerCmsElement: () => false,
        });

        expect(() =>
            registerCmsElement({
                apiVersion: 1,
                name: 'acme-recipe',
                labelSnippet: 'acme-recipe.cms.label',
                fields: [
                    {
                        name: 'title',
                        type: 'text',
                        labelSnippet: 'acme-recipe.cms.title',
                    },
                ],
            }),
        ).toThrow('[FroshJetpack Admin CMS] acme-recipe: Shopware rejected the registration');
    });
});
