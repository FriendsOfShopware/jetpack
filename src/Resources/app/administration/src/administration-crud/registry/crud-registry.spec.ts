import { CrudRegistry } from './crud-registry';
import type { CrudDefinition, CrudEntity, CrudExtension } from '../types';

function definition(overrides: Partial<CrudDefinition> = {}): CrudDefinition {
    return {
        apiVersion: 1,
        id: 'acme.review',
        entity: 'acme_review',
        snippets: 'acme-review',
        module: { titleSnippet: 'acme-review.general.title' },
        acl: { key: 'acme_review' },
        listing: {
            columns: [{ id: 'title', property: 'title' }],
        },
        detail: {
            cards: [
                {
                    id: 'general',
                    fields: [{ id: 'title', property: 'title', type: 'text' }],
                },
            ],
        },
        ...overrides,
    };
}

function extension(id: string, columnId: string, placement: { before?: string; after?: string }): CrudExtension {
    return {
        apiVersion: 1,
        id,
        listing: {
            columns: [
                {
                    id: columnId,
                    property: columnId,
                    ...placement,
                },
            ],
        },
    };
}

describe('CrudRegistry', () => {
    it('registers one atomic definition with predictable routes', () => {
        const registerModule = jest.fn();
        const registry = new CrudRegistry(registerModule);

        const registration = registry.register(definition());

        expect(registration).toEqual({
            id: 'acme.review',
            moduleName: 'acme-review',
            routeNames: {
                list: 'acme.review.list',
                create: 'acme.review.create',
                detail: 'acme.review.detail',
            },
        });
        expect(registerModule).toHaveBeenCalledWith(expect.objectContaining({ id: 'acme.review' }));
        expect(registry.get('acme.review')).toBeDefined();
    });

    it('rejects duplicate registrations before registering another module', () => {
        const registerModule = jest.fn();
        const registry = new CrudRegistry(registerModule);
        registry.register(definition());

        expect(() => registry.register(definition())).toThrow('registration already exists');
        expect(registerModule).toHaveBeenCalledTimes(1);
    });

    it('takes an immutable snapshot of a consumer definition', () => {
        const registry = new CrudRegistry(jest.fn());
        const consumerDefinition = definition();
        registry.register(consumerDefinition);

        if (consumerDefinition.listing === false) {
            throw new Error('Expected a listing definition');
        }
        consumerDefinition.listing.columns[0].id = 'changed';

        const registeredListing = registry.get('acme.review')?.listing;
        if (!registeredListing) {
            throw new Error('Expected a registered listing definition');
        }

        expect(registeredListing.columns[0].id).toBe('title');
        expect(Object.isFrozen(registeredListing.columns)).toBe(true);
        expect(() => {
            registeredListing.columns[0].id = 'forbidden';
        }).toThrow();
    });

    it('resolves extensions independently of plugin registration order', () => {
        const resolve = (extensions: CrudExtension[]): string[] => {
            const registry = new CrudRegistry(jest.fn());
            extensions.forEach((crudExtension) => registry.extend('acme.review', crudExtension));
            registry.register(definition());

            const listing = registry.get('acme.review')?.listing;
            if (!listing) {
                throw new Error('Expected a resolved listing');
            }

            return listing.columns.map((column) => column.id);
        };
        const extensions = [
            extension('zeta.extension', 'score', { after: 'title' }),
            extension('alpha.extension', 'status', { after: 'title' }),
        ];

        expect(resolve(extensions)).toEqual([
            'title',
            'status',
            'score',
        ]);
        expect(resolve([...extensions].reverse())).toEqual(resolve(extensions));
    });

    it('updates the resolved view and privileges for a late extension without registering another module', () => {
        const registerModule = jest.fn();
        const registerPrivileges = jest.fn();
        const registry = new CrudRegistry(registerModule, registerPrivileges);
        registry.register(definition());

        registry.extend('acme.review', extension('analytics.extension', 'score', { after: 'title' }));

        const listing = registry.get('acme.review')?.listing;
        expect(listing && listing.columns.map((column) => column.id)).toEqual([
            'title',
            'score',
        ]);
        expect(registerModule).toHaveBeenCalledTimes(1);
        expect(registerPrivileges).toHaveBeenCalledTimes(1);
    });

    it('rejects duplicate extension ids and unresolved placement references', () => {
        const registerModule = jest.fn();
        const registry = new CrudRegistry(registerModule);
        registry.extend('acme.review', extension('analytics.extension', 'score', { after: 'missing' }));

        expect(() => registry.extend('acme.other', extension('analytics.extension', 'other', {}))).toThrow(
            'extension already targets "acme.review"',
        );
        expect(() => registry.register(definition())).toThrow('references missing after id "missing"');
        expect(registerModule).not.toHaveBeenCalled();
    });

    it('validates association and collection fields', () => {
        const registry = new CrudRegistry(jest.fn());
        const invalid = definition({
            detail: {
                cards: [
                    {
                        id: 'relations',
                        fields: [
                            {
                                id: 'products',
                                property: 'products',
                                type: 'entity-multi-select',
                                entity: '',
                            },
                        ],
                    },
                ],
            },
        });

        expect(() => registry.register(invalid)).toThrow('detail.cards[0].fields[0].entity must not be empty');
    });

    it('validates translation and action contracts at runtime', () => {
        const registry = new CrudRegistry(jest.fn());
        const invalidTranslation = definition({
            translation: { enabled: false as true },
        });
        const invalidAction = definition({
            listing: {
                columns: [{ id: 'title', property: 'title' }],
                rowActions: [
                    {
                        id: 'publish',
                        labelSnippet: 'acme.publish',
                        requiredRole: 'administrator' as 'editor',
                        handler: jest.fn(),
                    },
                ],
            },
        });

        expect(() => registry.register(invalidTranslation)).toThrow('translation.enabled must be true');
        expect(() => registry.register(invalidAction)).toThrow(
            'listing.rowActions[0].requiredRole contains unknown role "administrator"',
        );
    });

    it('keeps the entity property type in consumer definitions', () => {
        type Review = CrudEntity & {
            title: string;
            productId: string | null;
        };

        const typed: CrudDefinition<Review> = {
            apiVersion: 1,
            id: 'acme.review',
            entity: 'acme_review',
            snippets: 'acme-review',
            module: { titleSnippet: 'acme-review.general.title' },
            acl: { key: 'acme_review' },
            listing: {
                columns: [{ id: 'title', property: 'title' }],
            },
            detail: {
                cards: [
                    {
                        id: 'general',
                        fields: [
                            {
                                id: 'product',
                                property: 'productId',
                                type: 'entity-select',
                                entity: 'product',
                            },
                        ],
                    },
                ],
            },
        };

        expect(typed.detail).not.toBe(false);
    });
});
