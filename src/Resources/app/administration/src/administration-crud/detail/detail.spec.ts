import { flushPromises, shallowMount } from '@vue/test-utils';
import CrudDetail from './index';
import type { CrudDetailActionContext, CrudEntity, FroshJetpackGlobal, ResolvedCrudDefinition } from '../types';

describe('CRUD detail', () => {
    it('loads to-many associations and reloads the entity after saving', async () => {
        const afterSave = jest.fn();
        const definition: ResolvedCrudDefinition = {
            apiVersion: 1,
            id: 'acme.review',
            entity: 'acme_review',
            snippets: 'acme-review',
            moduleName: 'acme-review',
            module: { titleSnippet: 'acme-review.general.title' },
            acl: { key: 'acme_review' },
            routeNames: {
                list: 'acme.review.list',
                detail: 'acme.review.detail',
            },
            listing: { columns: [{ id: 'title', property: 'title' }] },
            detail: {
                cards: [
                    {
                        id: 'relations',
                        fields: [
                            {
                                id: 'tags',
                                property: 'tags',
                                type: 'entity-multi-select',
                                entity: 'tag',
                            },
                        ],
                    },
                ],
            },
            hooks: { afterSave },
        };
        const loadedEntity: CrudEntity = { id: 'review-1', tags: [] };
        const reloadedEntity: CrudEntity = {
            id: 'review-1',
            tags: [{ id: 'tag-1' }],
        };
        const repository = {
            create: jest.fn(),
            get: jest.fn().mockResolvedValueOnce(loadedEntity).mockResolvedValueOnce(reloadedEntity),
            save: jest.fn().mockResolvedValue(undefined),
            delete: jest.fn(),
        };
        globalThis.FroshJetpack = {
            apiVersion: 1,
            runtimeVersion: 'test',
            Admin: {
                Crud: {
                    register: jest.fn(),
                    extend: jest.fn(),
                    get: () => definition,
                    all: () => [definition],
                },
            },
        } as FroshJetpackGlobal;

        const wrapper = shallowMount(CrudDetail, {
            props: { crudId: definition.id, entityId: 'review-1' },
            global: {
                mocks: {
                    $t: (key: string) => key,
                    $router: {
                        push: jest.fn(),
                        replace: jest.fn(),
                        back: jest.fn(),
                    },
                },
                provide: {
                    repositoryFactory: { create: () => repository },
                    acl: { can: () => true },
                },
                stubs: {
                    'sw-page': true,
                    'jetpack-button': true,
                    'jetpack-banner': true,
                    'jetpack-loader': true,
                    'jetpack-card': true,
                    'jetpack-entity-select': true,
                },
            },
        });
        await flushPromises();

        const getCalls = repository.get.mock.calls as Array<[string, unknown, { associations: string[] }]>;
        const loadCriteria = getCalls[0][2];
        expect(loadCriteria.associations).toEqual(['tags']);

        await wrapper.vm.save();

        expect(repository.save).toHaveBeenCalledWith(loadedEntity, Shopware.Context.api);
        expect(wrapper.vm.entity).toEqual(reloadedEntity);
        expect(afterSave).toHaveBeenCalledWith(
            expect.objectContaining({
                entity: reloadedEntity,
                mode: 'edit',
            }),
        );
    });

    it('creates entities in the system language context', async () => {
        const definition: ResolvedCrudDefinition = {
            apiVersion: 1,
            id: 'acme.review',
            entity: 'acme_review',
            snippets: 'acme-review',
            moduleName: 'acme-review',
            module: { titleSnippet: 'acme-review.general.title' },
            acl: { key: 'acme_review' },
            routeNames: { create: 'acme.review.create' },
            listing: false,
            detail: { cards: [{ id: 'general', fields: [{ id: 'title', property: 'title', type: 'text' }] }] },
            translation: { enabled: true },
        };
        const repository = {
            create: jest.fn().mockReturnValue({ id: 'new-review', title: '' }),
            get: jest.fn(),
            save: jest.fn(),
            delete: jest.fn(),
        };
        globalThis.FroshJetpack = {
            apiVersion: 1,
            runtimeVersion: 'test',
            Admin: {
                Crud: {
                    register: jest.fn(),
                    extend: jest.fn(),
                    get: () => definition,
                    all: () => [definition],
                },
            },
        } as FroshJetpackGlobal;
        Object.assign(Shopware.Context.api, {
            languageId: 'current-language',
            systemLanguageId: 'system-language',
        });

        shallowMount(CrudDetail, {
            props: { crudId: definition.id },
            global: {
                mocks: {
                    $t: (key: string) => key,
                    $router: { push: jest.fn(), replace: jest.fn(), back: jest.fn() },
                },
                provide: {
                    repositoryFactory: { create: () => repository },
                    acl: { can: () => true },
                },
                stubs: {
                    'sw-page': true,
                    'jetpack-button': true,
                    'jetpack-banner': true,
                    'jetpack-loader': true,
                    'jetpack-card': true,
                    'jetpack-entity-select': true,
                },
            },
        });
        await flushPromises();

        expect(repository.create).toHaveBeenCalledWith(
            expect.objectContaining({ languageId: 'system-language', systemLanguageId: 'system-language' }),
        );
    });

    it('loads translated contexts, exposes DAL property errors, and runs detail actions', async () => {
        const actionHandler = jest.fn();
        const field = { id: 'publishedAt', property: 'publishedAt', type: 'date' as const };
        const definition: ResolvedCrudDefinition = {
            apiVersion: 1,
            id: 'acme.review',
            entity: 'acme_review',
            snippets: 'acme-review',
            moduleName: 'acme-review',
            module: { titleSnippet: 'acme-review.general.title' },
            acl: { key: 'acme_review' },
            routeNames: { detail: 'acme.review.detail' },
            listing: false,
            detail: {
                cards: [{ id: 'general', fields: [field] }],
                actions: [{ id: 'publish', labelSnippet: 'acme.publish', handler: actionHandler }],
            },
            translation: { enabled: true },
        };
        const entity: CrudEntity & { getEntityName: () => string } = {
            id: 'review-1',
            publishedAt: null,
            getEntityName: () => 'acme_review',
        };
        const repository = {
            create: jest.fn(),
            get: jest.fn().mockResolvedValue(entity),
            save: jest.fn(),
            delete: jest.fn(),
        };
        globalThis.FroshJetpack = {
            apiVersion: 1,
            runtimeVersion: 'test',
            Admin: {
                Crud: {
                    register: jest.fn(),
                    extend: jest.fn(),
                    get: () => definition,
                    all: () => [definition],
                },
            },
        } as FroshJetpackGlobal;
        Object.assign(Shopware.Context.api, {
            languageId: 'en-language',
            systemLanguageId: 'system-language',
        });
        const host = Shopware as unknown as { Store?: unknown };
        host.Store = {
            get: () => ({
                getApiError: () => ({ detail: 'Choose a publication date.' }),
                removeApiError: jest.fn(),
            }),
        };

        const wrapper = shallowMount(CrudDetail, {
            props: { crudId: definition.id, entityId: entity.id },
            global: {
                mocks: {
                    $t: (key: string) => key,
                    $router: { push: jest.fn(), replace: jest.fn(), back: jest.fn() },
                },
                provide: {
                    repositoryFactory: { create: () => repository },
                    acl: { can: () => true },
                },
                stubs: {
                    'sw-page': true,
                    'jetpack-button': true,
                    'jetpack-banner': true,
                    'jetpack-loader': true,
                    'jetpack-card': true,
                    'jetpack-entity-select': true,
                },
            },
        });
        await flushPromises();

        expect(wrapper.vm.fieldComponent(field)).toBe('jetpack-datepicker');
        expect(wrapper.vm.fieldProps(field).error).toBe('Choose a publication date.');

        await wrapper.vm.changeLanguage('de-language');
        const getCalls = repository.get.mock.calls as Array<[string, { languageId: string }]>;
        expect(getCalls[1][1]).toEqual(expect.objectContaining({ languageId: 'de-language' }));

        const detailAction = wrapper.vm.detail.actions?.[0];
        if (!detailAction) {
            throw new Error('Expected a detail action');
        }
        await wrapper.vm.runDetailAction(detailAction);
        const actionCalls = actionHandler.mock.calls as Array<[CrudDetailActionContext<CrudEntity>]>;
        expect(actionCalls[0][0].entity).toEqual(entity);
        expect(actionCalls[0][0].apiContext).toEqual(expect.objectContaining({ languageId: 'de-language' }));

        delete host.Store;
    });
});
