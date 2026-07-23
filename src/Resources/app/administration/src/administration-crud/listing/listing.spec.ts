import { shallowMount } from '@vue/test-utils';
import CrudListing from './index';
import type { FroshJetpackGlobal, ResolvedCrudDefinition } from '../types';

describe('CRUD listing', () => {
    it('builds association-aware criteria and deletes through the DAL repository', async () => {
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
                create: 'acme.review.create',
                detail: 'acme.review.detail',
            },
            listing: {
                columns: [
                    {
                        id: 'product',
                        property: 'product.name',
                        sortable: true,
                    },
                ],
                associations: ['manufacturer'],
                defaultSort: [
                    { property: 'createdAt', direction: 'DESC' },
                ],
            },
            detail: {
                cards: [
                    {
                        id: 'general',
                        fields: [
                            { id: 'title', property: 'title', type: 'text' },
                        ],
                    },
                ],
            },
        };
        const repository = {
            delete: jest.fn().mockResolvedValue(undefined),
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

        const wrapper = shallowMount(CrudListing, {
            props: { crudId: definition.id },
            global: {
                mocks: {
                    $t: (key: string) => key,
                    $router: { push: jest.fn() },
                },
                provide: {
                    repositoryFactory: { create: () => repository },
                    acl: { can: () => true },
                },
                stubs: {
                    'sw-page': true,
                    'jetpack-button': true,
                    'jetpack-card': true,
                    'jetpack-text-field': true,
                    'jetpack-banner': true,
                    'jetpack-entity-table': true,
                    'jetpack-entity-select': true,
                    'jetpack-badge': true,
                    'jetpack-link': true,
                    'jetpack-modal': true,
                },
            },
        });

        const criteria = wrapper.vm.criteria as unknown as {
            associations: string[];
            sortings: Array<{ field: string; order: string }>;
        };
        expect(criteria.associations).toEqual([
            'manufacturer',
            'product',
        ]);
        expect(criteria.sortings).toEqual([
            {
                field: 'createdAt',
                order: 'DESC',
                naturalSorting: false,
            },
        ]);

        wrapper.vm.askDelete({ id: 'review-1' });
        await wrapper.vm.confirmDelete();

        expect(repository.delete).toHaveBeenCalledWith('review-1', Shopware.Context.api);
    });

    it('runs bulk and row actions and formats specialized columns', async () => {
        const bulkHandler = jest.fn();
        const rowHandler = jest.fn();
        const definition: ResolvedCrudDefinition = {
            ...({
                apiVersion: 1,
                id: 'acme.review',
                entity: 'acme_review',
                snippets: 'acme-review',
                moduleName: 'acme-review',
                module: { titleSnippet: 'acme-review.general.title' },
                acl: { key: 'acme_review' },
                routeNames: { list: 'acme.review.list' },
                listing: {
                    columns: [{ id: 'title', property: 'title' }],
                    bulk: {
                        delete: true,
                        actions: [{ id: 'publish', labelSnippet: 'acme.publish', handler: bulkHandler }],
                    },
                    rowActions: [{ id: 'inspect', labelSnippet: 'acme.inspect', handler: rowHandler }],
                },
                detail: false,
            } satisfies ResolvedCrudDefinition),
        };
        const repository = {
            create: jest.fn(),
            get: jest.fn(),
            save: jest.fn(),
            delete: jest.fn(),
            syncDeleted: jest.fn().mockResolvedValue(undefined),
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
        (Shopware.Context as unknown as { app: Record<string, unknown> }).app = {
            config: { locale: 'en-GB' },
            systemCurrencyISOCode: 'EUR',
        };

        const wrapper = shallowMount(CrudListing, {
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
                    'jetpack-card': true,
                    'jetpack-text-field': true,
                    'jetpack-banner': true,
                    'jetpack-entity-table': true,
                    'jetpack-entity-select': true,
                    'jetpack-badge': true,
                    'jetpack-link': true,
                    'jetpack-modal': true,
                },
            },
        });
        const entities = [
            { id: 'review-1', title: 'One' },
            { id: 'review-2', title: 'Two' },
        ];
        wrapper.vm.onLoad(Object.assign(entities, { total: 2 }));
        wrapper.vm.selectedIds = [
            'review-1',
            'review-2',
        ];

        wrapper.vm.askBulkDelete();
        await wrapper.vm.confirmBulkAction();

        expect(repository.syncDeleted).toHaveBeenCalledWith(
            [
                'review-1',
                'review-2',
            ],
            expect.objectContaining({}),
        );

        wrapper.vm.onLoad(Object.assign(entities, { total: 2 }));
        wrapper.vm.selectedIds = ['review-1'];
        const bulkAction = wrapper.vm.bulkActions[0];
        await wrapper.vm.askBulkAction(bulkAction);
        expect(bulkHandler).toHaveBeenCalledWith(
            expect.objectContaining({
                selectedIds: ['review-1'],
                entities: [entities[0]],
            }),
        );

        const rowAction = wrapper.vm.listing.rowActions?.[0];
        if (!rowAction) {
            throw new Error('Expected a row action');
        }
        await wrapper.vm.runRowAction(rowAction, entities[0]);
        expect(rowHandler).toHaveBeenCalledWith(expect.objectContaining({ entity: entities[0] }));

        expect(wrapper.vm.formatColumnValue({ id: 'active', property: 'active', type: 'boolean' }, true)).toBe(
            'frosh-jetpack-crud.values.yes',
        );
        expect(wrapper.vm.formatColumnValue({ id: 'price', property: 'price', type: 'currency' }, 12)).toContain('12');
        expect(
            wrapper.vm.badgeVariant(
                {
                    id: 'status',
                    property: 'status',
                    type: 'badge',
                    badges: { published: { variant: 'positive' } },
                },
                'published',
            ),
        ).toBe('positive');
    });
});
