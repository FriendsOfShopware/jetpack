import { applyCrudExtensions } from './extension-resolver';
import type { CrudDefinition, CrudExtension } from '../types';

const definition: CrudDefinition = {
    apiVersion: 1,
    id: 'acme.review',
    entity: 'acme_review',
    snippets: 'acme-review',
    module: { titleSnippet: 'acme-review.general.title' },
    acl: { key: 'acme_review' },
    listing: { columns: [{ id: 'title', property: 'title' }] },
    detail: {
        cards: [
            {
                id: 'general',
                fields: [{ id: 'title', property: 'title', type: 'text' }],
            },
        ],
    },
};

describe('CRUD extension resolver', () => {
    it('adds cards, fields, actions, and bulk actions to their stable targets', () => {
        const action = jest.fn();
        const extension: CrudExtension = {
            apiVersion: 1,
            id: 'analytics.extension',
            listing: {
                bulkActions: [{ id: 'analyse', labelSnippet: 'analytics.analyse', handler: action }],
            },
            detail: {
                cards: [
                    {
                        id: 'analytics',
                        after: 'general',
                        fields: [{ id: 'score', property: 'score', type: 'number' }],
                    },
                ],
                fields: [
                    {
                        id: 'status',
                        card: 'general',
                        after: 'title',
                        property: 'status',
                        type: 'text',
                    },
                ],
                actions: [{ id: 'refresh', labelSnippet: 'analytics.refresh', handler: action }],
            },
        };

        const resolved = applyCrudExtensions(definition, [extension]);
        if (resolved.listing === false || resolved.detail === false) {
            throw new Error('Expected listing and detail definitions');
        }

        expect(resolved.listing.bulk && resolved.listing.bulk.actions?.map(({ id }) => id)).toEqual(['analyse']);
        expect(resolved.detail.cards.map(({ id }) => id)).toEqual([
            'general',
            'analytics',
        ]);
        expect(resolved.detail.cards[0].fields.map(({ id }) => id)).toEqual([
            'title',
            'status',
        ]);
        expect(resolved.detail.actions?.map(({ id }) => id)).toEqual(['refresh']);
    });

    it('rejects ordering cycles with a precise diagnostic', () => {
        const cyclic: CrudExtension = {
            apiVersion: 1,
            id: 'analytics.extension',
            listing: {
                columns: [
                    { id: 'first', property: 'first', after: 'second' },
                    { id: 'second', property: 'second', after: 'first' },
                ],
            },
        };

        expect(() => applyCrudExtensions(definition, [cyclic])).toThrow(
            'contains an ordering cycle involving "first", "second"',
        );
    });

    it('does not let an extension override an explicit bulk opt-out', () => {
        const extension: CrudExtension = {
            apiVersion: 1,
            id: 'analytics.extension',
            listing: {
                bulkActions: [{ id: 'analyse', labelSnippet: 'analytics.analyse', handler: jest.fn() }],
            },
        };
        const bulkDisabled: CrudDefinition = {
            ...definition,
            listing: { columns: [{ id: 'title', property: 'title' }], bulk: false },
        };

        expect(() => applyCrudExtensions(bulkDisabled, [extension])).toThrow(
            'cannot extend explicitly disabled bulk actions',
        );
    });
});
