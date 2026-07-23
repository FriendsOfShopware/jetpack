import type { CrudColumn, CrudDefinition, CrudEntity, CrudExtension, CrudField, CrudPlacement } from '../types';

const REGISTRATION_ID = /^[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)+$/;
const COLUMN_TYPES = new Set([
    'text',
    'number',
    'currency',
    'boolean',
    'date',
    'badge',
    'custom',
]);
const ACTION_ROLES = new Set([
    'viewer',
    'creator',
    'editor',
    'deleter',
]);
const ACTION_VARIANTS = new Set([
    'primary',
    'secondary',
    'tertiary',
    'critical',
]);
const FIELD_TYPES = new Set([
    'text',
    'email',
    'url',
    'password',
    'number',
    'textarea',
    'checkbox',
    'switch',
    'date',
    'color',
    'rich-text',
    'select',
    'multi-select',
    'entity-select',
    'entity-multi-select',
    'custom',
]);

function fail(id: string, path: string, message: string): never {
    throw new Error(`[FroshJetpack Admin CRUD] ${id}: ${path} ${message}`);
}

function assertUniqueIds(registrationId: string, path: string, items: Array<{ id: string }>): void {
    const ids = new Set<string>();

    items.forEach((item, index) => {
        if (!item.id.trim()) {
            fail(registrationId, `${path}[${index}].id`, 'must not be empty');
        }

        if (ids.has(item.id)) {
            fail(registrationId, path, `contains duplicate id "${item.id}"`);
        }

        ids.add(item.id);
    });
}

function validatePlacement(registrationId: string, path: string, placement: CrudPlacement): void {
    if (placement.before && placement.after) {
        fail(registrationId, path, 'must not define both before and after');
    }
    if (placement.position !== undefined && !Number.isFinite(placement.position)) {
        fail(registrationId, `${path}.position`, 'must be a finite number');
    }
}

function validateColumn<TEntity extends CrudEntity>(
    registrationId: string,
    path: string,
    column: CrudColumn<TEntity>,
): void {
    if (!String(column.property).trim()) {
        fail(registrationId, `${path}.property`, 'must not be empty');
    }
    if (column.type && !COLUMN_TYPES.has(column.type)) {
        fail(registrationId, `${path}.type`, `contains unknown renderer "${column.type}"`);
    }
    if (column.type === 'custom' && !column.component?.trim()) {
        fail(registrationId, `${path}.component`, 'must not be empty for a custom column');
    }
}

function validateAction(
    registrationId: string,
    path: string,
    action: {
        labelSnippet: string;
        handler: unknown;
        requiredRole?: string;
        privilege?: string;
        variant?: string;
        confirmation?: { titleSnippet: string; messageSnippet: string };
    },
): void {
    if (!action.labelSnippet.trim()) {
        fail(registrationId, `${path}.labelSnippet`, 'must not be empty');
    }
    if (typeof action.handler !== 'function') {
        fail(registrationId, `${path}.handler`, 'must be a function');
    }
    if (action.requiredRole && !ACTION_ROLES.has(action.requiredRole)) {
        fail(registrationId, `${path}.requiredRole`, `contains unknown role "${action.requiredRole}"`);
    }
    if (action.privilege !== undefined && !action.privilege.trim()) {
        fail(registrationId, `${path}.privilege`, 'must not be empty');
    }
    if (action.variant && !ACTION_VARIANTS.has(action.variant)) {
        fail(registrationId, `${path}.variant`, `contains unknown variant "${action.variant}"`);
    }
    if (action.confirmation && (!action.confirmation.titleSnippet.trim() || !action.confirmation.messageSnippet.trim())) {
        fail(registrationId, `${path}.confirmation`, 'must contain titleSnippet and messageSnippet');
    }
}

function validateField<TEntity extends CrudEntity>(registrationId: string, path: string, field: CrudField<TEntity>): void {
    if (!String(field.property).trim()) {
        fail(registrationId, `${path}.property`, 'must not be empty');
    }

    if (!FIELD_TYPES.has(field.type)) {
        fail(registrationId, `${path}.type`, `contains unknown renderer "${field.type}"`);
    }

    if ((field.type === 'select' || field.type === 'multi-select') && field.options.length === 0) {
        fail(registrationId, `${path}.options`, 'must contain an option');
    }

    if ((field.type === 'entity-select' || field.type === 'entity-multi-select') && !field.entity.trim()) {
        fail(registrationId, `${path}.entity`, 'must not be empty');
    }

    if (field.type === 'custom' && !field.component.trim()) {
        fail(registrationId, `${path}.component`, 'must not be empty');
    }
}

export function validateCrudDefinition<TEntity extends CrudEntity>(definition: CrudDefinition<TEntity>): void {
    const id = definition.id || '<unknown>';

    if (definition.apiVersion !== 1) {
        fail(id, 'apiVersion', 'must be 1');
    }

    if (!REGISTRATION_ID.test(definition.id)) {
        fail(id, 'id', 'must contain at least two lower-case dot-separated segments');
    }

    if (!definition.entity.trim()) {
        fail(id, 'entity', 'must not be empty');
    }

    if (!definition.snippets.trim()) {
        fail(id, 'snippets', 'must not be empty');
    }

    if (!definition.module.titleSnippet.trim()) {
        fail(id, 'module.titleSnippet', 'must not be empty');
    }

    if (!definition.acl.key.trim()) {
        fail(id, 'acl.key', 'must not be empty');
    }

    if (definition.listing === false && definition.detail === false) {
        fail(id, 'listing/detail', 'must enable at least one page');
    }

    if (definition.translation !== undefined && definition.translation !== false) {
        if (definition.translation.enabled !== true) {
            fail(id, 'translation.enabled', 'must be true');
        }
        if (
            definition.translation.languageLabelProperty !== undefined &&
            !definition.translation.languageLabelProperty.trim()
        ) {
            fail(id, 'translation.languageLabelProperty', 'must not be empty');
        }
    }

    if (definition.listing !== false) {
        if (definition.listing.columns.length === 0) {
            fail(id, 'listing.columns', 'must contain a column');
        }

        assertUniqueIds(id, 'listing.columns', definition.listing.columns);
        definition.listing.columns.forEach((column, index) => validateColumn(id, `listing.columns[${index}]`, column));

        if ((definition.listing.pageSize ?? 25) < 1) {
            fail(id, 'listing.pageSize', 'must be greater than zero');
        }

        assertUniqueIds(id, 'listing.rowActions', definition.listing.rowActions ?? []);
        definition.listing.rowActions?.forEach((action, index) =>
            validateAction(id, `listing.rowActions[${index}]`, action),
        );

        const bulkActions = definition.listing.bulk === false ? [] : (definition.listing.bulk?.actions ?? []);
        assertUniqueIds(id, 'listing.bulk.actions', bulkActions);
        bulkActions.forEach((action, index) => validateAction(id, `listing.bulk.actions[${index}]`, action));
    }

    if (definition.detail !== false) {
        if (definition.detail.cards.length === 0) {
            fail(id, 'detail.cards', 'must contain a card');
        }

        assertUniqueIds(id, 'detail.cards', definition.detail.cards);
        const fieldIds: Array<{ id: string }> = [];

        definition.detail.cards.forEach((card, cardIndex) => {
            if (card.fields.length === 0) {
                fail(id, `detail.cards[${cardIndex}].fields`, 'must contain a field');
            }

            assertUniqueIds(id, `detail.cards[${cardIndex}].fields`, card.fields);
            fieldIds.push(...card.fields);
            card.fields.forEach((field, fieldIndex) =>
                validateField(id, `detail.cards[${cardIndex}].fields[${fieldIndex}]`, field),
            );
        });

        assertUniqueIds(id, 'detail fields', fieldIds);
        assertUniqueIds(id, 'detail.actions', definition.detail.actions ?? []);
        definition.detail.actions?.forEach((action, index) => validateAction(id, `detail.actions[${index}]`, action));
    }
}

export function validateCrudExtension<TEntity extends CrudEntity>(target: string, extension: CrudExtension<TEntity>): void {
    if (!REGISTRATION_ID.test(target)) {
        fail(target || '<unknown>', 'target', 'must contain at least two lower-case dot-separated segments');
    }

    const id = extension.id || '<unknown>';
    if (extension.apiVersion !== 1) {
        fail(id, 'apiVersion', 'must be 1');
    }
    if (!REGISTRATION_ID.test(extension.id)) {
        fail(id, 'id', 'must contain at least two lower-case dot-separated segments');
    }
    if (!extension.listing && !extension.detail) {
        fail(id, 'listing/detail', 'must add at least one element');
    }

    const columns = extension.listing?.columns ?? [];
    assertUniqueIds(id, 'listing.columns', columns);
    columns.forEach((column, index) => {
        validatePlacement(id, `listing.columns[${index}]`, column);
        validateColumn(id, `listing.columns[${index}]`, column);
    });

    const rowActions = extension.listing?.rowActions ?? [];
    assertUniqueIds(id, 'listing.rowActions', rowActions);
    rowActions.forEach((action, index) => {
        validatePlacement(id, `listing.rowActions[${index}]`, action);
        validateAction(id, `listing.rowActions[${index}]`, action);
    });

    const bulkActions = extension.listing?.bulkActions ?? [];
    assertUniqueIds(id, 'listing.bulkActions', bulkActions);
    bulkActions.forEach((action, index) => {
        validatePlacement(id, `listing.bulkActions[${index}]`, action);
        validateAction(id, `listing.bulkActions[${index}]`, action);
    });

    const cards = extension.detail?.cards ?? [];
    assertUniqueIds(id, 'detail.cards', cards);
    cards.forEach((card, cardIndex) => {
        validatePlacement(id, `detail.cards[${cardIndex}]`, card);
        if (card.fields.length === 0) {
            fail(id, `detail.cards[${cardIndex}].fields`, 'must contain a field');
        }
        assertUniqueIds(id, `detail.cards[${cardIndex}].fields`, card.fields);
        card.fields.forEach((field, fieldIndex) =>
            validateField(id, `detail.cards[${cardIndex}].fields[${fieldIndex}]`, field),
        );
    });

    const fields = extension.detail?.fields ?? [];
    assertUniqueIds(id, 'detail.fields', fields);
    fields.forEach((field, index) => {
        validatePlacement(id, `detail.fields[${index}]`, field);
        if (!field.card.trim()) {
            fail(id, `detail.fields[${index}].card`, 'must not be empty');
        }
        validateField(id, `detail.fields[${index}]`, field);
    });

    const actions = extension.detail?.actions ?? [];
    assertUniqueIds(id, 'detail.actions', actions);
    actions.forEach((action, index) => {
        validatePlacement(id, `detail.actions[${index}]`, action);
        validateAction(id, `detail.actions[${index}]`, action);
    });
}
