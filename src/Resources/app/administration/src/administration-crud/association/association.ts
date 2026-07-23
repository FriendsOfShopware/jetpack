import type { CrudCriteria, CrudDetailDefinition, CrudEntity, CrudListingDefinition } from '../types';

export type EntityRecord = Record<string, unknown> & { id: string };

export type MutableEntityCollection = EntityRecord[] & {
    add?: (entity: EntityRecord) => void;
    remove?: (id: string) => boolean;
    getIds?: () => string[];
};

export function collectListingAssociations(listing: CrudListingDefinition): string[] {
    const associations = new Set(listing.associations ?? []);

    listing.columns.forEach((column) => {
        if (column.association) {
            associations.add(column.association);
            return;
        }

        const segments = String(column.property).split('.');
        if (segments.length > 1) {
            associations.add(segments.slice(0, -1).join('.'));
        }
    });

    return Array.from(associations);
}

export function collectDetailAssociations(detail: CrudDetailDefinition): string[] {
    const associations = new Set(detail.associations ?? []);

    detail.cards.forEach((card) => {
        card.fields.forEach((field) => {
            if (field.type === 'entity-multi-select') {
                associations.add(String(field.property));
            }
        });
    });

    return Array.from(associations);
}

export function applyAssociations(criteria: CrudCriteria, associations: string[]): void {
    associations.forEach((association) => criteria.addAssociation(association));
}

export function selectedIds(collection: MutableEntityCollection): string[] {
    return collection.getIds?.() ?? collection.map((entity) => entity.id);
}

export function syncEntityCollection(
    collection: MutableEntityCollection,
    ids: string[],
    available: EntityRecord[],
): MutableEntityCollection {
    const selected = new Set(ids);

    [...collection].forEach((entity) => {
        if (selected.has(entity.id)) {
            return;
        }

        if (collection.remove) {
            collection.remove(entity.id);
            return;
        }

        const index = collection.findIndex((candidate) => candidate.id === entity.id);
        if (index >= 0) {
            collection.splice(index, 1);
        }
    });

    const current = new Set(collection.map((entity) => entity.id));
    ids.forEach((id) => {
        if (current.has(id)) {
            return;
        }

        const entity = available.find((candidate) => candidate.id === id);
        if (!entity) {
            throw new Error(`[FroshJetpack Admin CRUD] Cannot add association entity "${id}" because it was not loaded`);
        }

        if (collection.add) {
            collection.add(entity);
        } else {
            collection.push(entity);
        }
        current.add(id);
    });

    return collection;
}

export function associationReadPrivileges<TEntity extends CrudEntity>(definition: {
    listing: CrudListingDefinition<TEntity> | false;
    detail: CrudDetailDefinition<TEntity> | false;
}): string[] {
    const entities = new Set<string>();

    if (definition.listing !== false) {
        definition.listing.columns.forEach((column) => {
            if (column.associationEntity) {
                entities.add(column.associationEntity);
            }
        });
    }

    if (definition.detail !== false) {
        definition.detail.cards.forEach((card) => {
            card.fields.forEach((field) => {
                if (field.type === 'entity-select' || field.type === 'entity-multi-select') {
                    entities.add(field.entity);
                }
            });
        });
    }

    return Array.from(entities, (entity) => `${entity}:read`);
}
