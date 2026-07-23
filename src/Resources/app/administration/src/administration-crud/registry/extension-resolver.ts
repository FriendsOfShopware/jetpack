import type {
    CrudBulkAction,
    CrudCard,
    CrudColumn,
    CrudDefinition,
    CrudDetailAction,
    CrudEntity,
    CrudExtension,
    CrudField,
    CrudPlacement,
    CrudRowAction,
} from '../types';

type Identified = { id: string };
type Addition<T extends Identified> = {
    item: T & CrudPlacement;
    owner: string;
};

type OrderedNode<T extends Identified> = {
    item: T & CrudPlacement;
    owner: string;
    baseIndex: number | null;
    outgoing: Set<string>;
    incoming: number;
};

function fail(registrationId: string, path: string, message: string): never {
    throw new Error(`[FroshJetpack Admin CRUD] ${registrationId}: ${path} ${message}`);
}

function stripPlacement<T extends Identified>(item: T & CrudPlacement): T {
    const resolved = { ...item };
    delete resolved.before;
    delete resolved.after;
    delete resolved.position;

    return resolved as T;
}

function mergeOrdered<T extends Identified>(
    registrationId: string,
    path: string,
    base: T[],
    additions: Array<Addition<T>>,
): T[] {
    const nodes = new Map<string, OrderedNode<T>>();

    base.forEach((item, index) => {
        nodes.set(item.id, {
            item,
            owner: registrationId,
            baseIndex: index,
            outgoing: new Set<string>(),
            incoming: 0,
        });
    });

    additions
        .slice()
        .sort((left, right) => left.owner.localeCompare(right.owner) || left.item.id.localeCompare(right.item.id))
        .forEach(({ item, owner }) => {
            const existing = nodes.get(item.id);
            if (existing) {
                fail(registrationId, path, `contains duplicate id "${item.id}" from "${existing.owner}" and "${owner}"`);
            }

            nodes.set(item.id, {
                item,
                owner,
                baseIndex: null,
                outgoing: new Set<string>(),
                incoming: 0,
            });
        });

    const connect = (from: string, to: string): void => {
        const source = nodes.get(from);
        const target = nodes.get(to);
        if (!source || !target || source.outgoing.has(to)) {
            return;
        }

        source.outgoing.add(to);
        target.incoming += 1;
    };

    for (let index = 0; index < base.length - 1; index += 1) {
        connect(base[index].id, base[index + 1].id);
    }

    additions.forEach(({ item, owner }) => {
        if (item.before && !nodes.has(item.before)) {
            fail(registrationId, path, `item "${item.id}" from "${owner}" references missing before id "${item.before}"`);
        }
        if (item.after && !nodes.has(item.after)) {
            fail(registrationId, path, `item "${item.id}" from "${owner}" references missing after id "${item.after}"`);
        }

        if (item.before) {
            connect(item.id, item.before);
        }
        if (item.after) {
            connect(item.after, item.id);
        }
    });

    const weightCache = new Map<string, number>();
    const weight = (id: string, visiting = new Set<string>()): number => {
        const cached = weightCache.get(id);
        if (cached !== undefined) {
            return cached;
        }

        const node = nodes.get(id);
        if (!node) {
            return Number.MAX_SAFE_INTEGER;
        }
        if (typeof node.item.position === 'number') {
            weightCache.set(id, node.item.position);
            return node.item.position;
        }
        if (node.baseIndex !== null) {
            const baseWeight = node.baseIndex * 100;
            weightCache.set(id, baseWeight);
            return baseWeight;
        }
        if (visiting.has(id)) {
            return Number.MAX_SAFE_INTEGER;
        }

        visiting.add(id);
        const relative = node.item.before
            ? weight(node.item.before, visiting) - 1
            : node.item.after
              ? weight(node.item.after, visiting) + 1
              : Number.MAX_SAFE_INTEGER;
        visiting.delete(id);
        weightCache.set(id, relative);

        return relative;
    };

    const compare = (left: OrderedNode<T>, right: OrderedNode<T>): number =>
        weight(left.item.id) - weight(right.item.id) ||
        left.owner.localeCompare(right.owner) ||
        left.item.id.localeCompare(right.item.id);

    const available = Array.from(nodes.values())
        .filter((node) => node.incoming === 0)
        .sort(compare);
    const result: T[] = [];

    while (available.length > 0) {
        const node = available.shift();
        if (!node) {
            break;
        }

        result.push(stripPlacement(node.item));
        node.outgoing.forEach((targetId) => {
            const target = nodes.get(targetId);
            if (!target) {
                return;
            }

            target.incoming -= 1;
            if (target.incoming === 0) {
                available.push(target);
                available.sort(compare);
            }
        });
    }

    if (result.length !== nodes.size) {
        const unresolved = Array.from(nodes.values())
            .filter((node) => node.incoming > 0)
            .map((node) => node.item.id)
            .sort();
        fail(registrationId, path, `contains an ordering cycle involving "${unresolved.join('", "')}"`);
    }

    return result;
}

function additionsFor<TEntity extends CrudEntity, T extends Identified>(
    extensions: Array<CrudExtension<TEntity>>,
    select: (extension: CrudExtension<TEntity>) => Array<T & CrudPlacement> | undefined,
): Array<Addition<T>> {
    return extensions.flatMap((extension) =>
        (select(extension) ?? []).map((item) => ({
            item,
            owner: extension.id,
        })),
    );
}

function assertUniqueFields<TEntity extends CrudEntity>(registrationId: string, cards: Array<CrudCard<TEntity>>): void {
    const owners = new Map<string, string>();
    cards.forEach((card) => {
        card.fields.forEach((field) => {
            const existing = owners.get(field.id);
            if (existing) {
                fail(
                    registrationId,
                    'detail fields',
                    `contains duplicate id "${field.id}" in cards "${existing}" and "${card.id}"`,
                );
            }
            owners.set(field.id, card.id);
        });
    });
}

export function applyCrudExtensions<TEntity extends CrudEntity>(
    definition: CrudDefinition<TEntity>,
    extensions: Array<CrudExtension<TEntity>>,
): CrudDefinition<TEntity> {
    const orderedExtensions = extensions.slice().sort((left, right) => left.id.localeCompare(right.id));
    const hasListingExtensions = orderedExtensions.some((extension) => extension.listing !== undefined);
    const hasDetailExtensions = orderedExtensions.some((extension) => extension.detail !== undefined);

    if (definition.listing === false && hasListingExtensions) {
        fail(definition.id, 'extensions.listing', 'cannot extend a disabled listing');
    }
    if (definition.detail === false && hasDetailExtensions) {
        fail(definition.id, 'extensions.detail', 'cannot extend a disabled detail page');
    }

    let listing = definition.listing;
    if (listing !== false) {
        const bulkActionAdditions = additionsFor<TEntity, CrudBulkAction<TEntity>>(
            orderedExtensions,
            (extension) => extension.listing?.bulkActions,
        );
        if (listing.bulk === false && bulkActionAdditions.length > 0) {
            fail(definition.id, 'listing.bulk.actions', 'cannot extend explicitly disabled bulk actions');
        }

        const columns = mergeOrdered<CrudColumn<TEntity>>(
            definition.id,
            'listing.columns',
            listing.columns,
            additionsFor<TEntity, CrudColumn<TEntity>>(orderedExtensions, (extension) => extension.listing?.columns),
        );
        const rowActions = mergeOrdered<CrudRowAction<TEntity>>(
            definition.id,
            'listing.rowActions',
            listing.rowActions ?? [],
            additionsFor<TEntity, CrudRowAction<TEntity>>(orderedExtensions, (extension) => extension.listing?.rowActions),
        );
        const bulkActions = mergeOrdered<CrudBulkAction<TEntity>>(
            definition.id,
            'listing.bulk.actions',
            listing.bulk === false ? [] : (listing.bulk?.actions ?? []),
            bulkActionAdditions,
        );
        const hasBulkActions = bulkActions.length > 0;

        listing = {
            ...listing,
            columns,
            ...(rowActions.length > 0 ? { rowActions } : {}),
            ...(hasBulkActions
                ? {
                      bulk: {
                          ...(listing.bulk === false ? {} : listing.bulk),
                          actions: bulkActions,
                      },
                  }
                : {}),
        };
    }

    let detail = definition.detail;
    if (detail !== false) {
        const cards = mergeOrdered<CrudCard<TEntity>>(
            definition.id,
            'detail.cards',
            detail.cards,
            additionsFor<TEntity, CrudCard<TEntity>>(orderedExtensions, (extension) => extension.detail?.cards),
        );
        const fieldsByCard = new Map<string, Array<Addition<CrudField<TEntity>>>>();
        orderedExtensions.forEach((extension) => {
            extension.detail?.fields?.forEach((field) => {
                const { card, ...placedField } = field;
                const additions = fieldsByCard.get(card) ?? [];
                additions.push({
                    item: placedField as CrudField<TEntity> & CrudPlacement,
                    owner: extension.id,
                });
                fieldsByCard.set(card, additions);
            });
        });

        fieldsByCard.forEach((_fields, cardId) => {
            if (!cards.some((card) => card.id === cardId)) {
                fail(definition.id, 'detail.fields', `references missing card "${cardId}"`);
            }
        });

        const resolvedCards = cards.map((card) => ({
            ...card,
            fields: mergeOrdered<CrudField<TEntity>>(
                definition.id,
                `detail.cards.${card.id}.fields`,
                card.fields,
                fieldsByCard.get(card.id) ?? [],
            ),
        }));
        assertUniqueFields(definition.id, resolvedCards);

        const actions = mergeOrdered<CrudDetailAction<TEntity>>(
            definition.id,
            'detail.actions',
            detail.actions ?? [],
            additionsFor<TEntity, CrudDetailAction<TEntity>>(orderedExtensions, (extension) => extension.detail?.actions),
        );

        detail = {
            ...detail,
            cards: resolvedCards,
            ...(actions.length > 0 ? { actions } : {}),
        };
    }

    return {
        ...definition,
        listing,
        detail,
    };
}
