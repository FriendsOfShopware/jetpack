import {
    associationReadPrivileges,
    collectDetailAssociations,
    collectListingAssociations,
    syncEntityCollection,
    type EntityRecord,
    type MutableEntityCollection,
} from './association';

describe('CRUD associations', () => {
    it('collects nested listing and to-many detail associations', () => {
        expect(
            collectListingAssociations({
                associations: ['manufacturer'],
                columns: [
                    { id: 'title', property: 'title' },
                    { id: 'product', property: 'product.name' },
                    {
                        id: 'country',
                        property: 'address.country.name',
                        association: 'address.country',
                    },
                ],
            }),
        ).toEqual([
            'manufacturer',
            'product',
            'address.country',
        ]);

        expect(
            collectDetailAssociations({
                associations: ['product'],
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
            }),
        ).toEqual([
            'product',
            'tags',
        ]);
    });

    it('adds and removes loaded entities through the DAL collection contract', () => {
        const collection = [
            { id: 'one' },
            { id: 'two' },
        ] as MutableEntityCollection;
        collection.add = (entity: EntityRecord) => collection.push(entity);
        collection.remove = (id: string) => {
            const index = collection.findIndex((entity) => entity.id === id);
            collection.splice(index, 1);
            return true;
        };

        syncEntityCollection(
            collection,
            [
                'two',
                'three',
            ],
            [
                { id: 'three', name: 'Third' },
            ],
        );

        expect(collection.map((entity) => entity.id)).toEqual([
            'two',
            'three',
        ]);
    });

    it('derives target entity read privileges from association fields and columns', () => {
        expect(
            associationReadPrivileges({
                listing: {
                    columns: [
                        {
                            id: 'product',
                            property: 'product.name',
                            associationEntity: 'product',
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
            }),
        ).toEqual([
            'product:read',
            'tag:read',
        ]);
    });
});
