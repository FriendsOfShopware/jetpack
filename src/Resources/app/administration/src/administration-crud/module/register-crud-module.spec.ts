import { registerCrudModule } from './register-crud-module';
import type { ResolvedCrudDefinition } from '../types';

describe('registerCrudModule', () => {
    beforeEach(() => {
        (Shopware.Module.register as jest.Mock).mockClear();
        (Shopware.Service as jest.Mock).mockClear();
    });

    it('registers routes and association-aware ACL roles', () => {
        const definition: ResolvedCrudDefinition = {
            apiVersion: 1,
            id: 'acme.review',
            entity: 'acme_review',
            snippets: 'acme-review',
            moduleName: 'acme-review',
            module: {
                titleSnippet: 'acme-review.general.title',
                navigation: { parent: 'sw-catalogue' },
            },
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
                        associationEntity: 'product',
                    },
                ],
                rowActions: [
                    {
                        id: 'moderate',
                        labelSnippet: 'acme-review.actions.moderate',
                        privilege: 'acme_review:moderate',
                        handler: jest.fn(),
                    },
                ],
            },
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
        };

        registerCrudModule(definition);

        const privilegeService = (Shopware.Service as jest.Mock).mock.results[0].value as {
            addPrivilegeMappingEntry: jest.Mock;
        };
        type PrivilegeMapping = {
            key: string;
            roles: {
                viewer: { privileges: string[] };
                editor: { privileges: string[] };
            };
        };
        const privilegeCalls = privilegeService.addPrivilegeMappingEntry.mock.calls as Array<[PrivilegeMapping]>;
        expect(privilegeCalls[0][0].key).toBe('acme_review');
        expect(privilegeCalls[0][0].roles.viewer.privileges).toEqual([
            'acme_review:read',
            'product:read',
            'tag:read',
        ]);
        expect(privilegeCalls[0][0].roles.editor.privileges).toContain('acme_review:moderate');

        type RegisteredModule = {
            routes: Record<string, { path: string }>;
        };
        const moduleCalls = (Shopware.Module.register as jest.Mock).mock.calls as Array<[string, RegisteredModule]>;
        expect(moduleCalls[0][0]).toBe('acme-review');
        expect(moduleCalls[0][1].routes.list.path).toBe('list');
        expect(moduleCalls[0][1].routes.create.path).toBe('create');
        expect(moduleCalls[0][1].routes.detail.path).toBe('detail/:id');
    });
});
