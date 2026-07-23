import type { CrudApiContext, CrudEntity } from '../types';

export type CrudPropertyError = {
    code?: string;
    detail?: string;
};

type ErrorStore = {
    getApiError: (entity: CrudEntity, property: string) => CrudPropertyError | null;
    removeApiError: (expression: string) => void;
};

type CompatibilityHost = {
    Store?: {
        get: (name: string) => ErrorStore;
    };
    State?: {
        getters?: Record<string, ((entity: CrudEntity, property: string) => CrudPropertyError | null) | undefined>;
        dispatch?: (action: string, payload?: unknown) => Promise<unknown> | void;
    };
};

type DalEntity = CrudEntity & {
    getEntityName?: () => string;
};

export function createApiContext(languageId?: string | null): CrudApiContext {
    return {
        ...(Shopware.Context.api as CrudApiContext),
        ...(languageId ? { languageId } : {}),
    };
}

export function initialLanguageId(): string {
    const context = Shopware.Context.api as CrudApiContext;

    return context.languageId ?? context.systemLanguageId ?? '';
}

export function systemLanguageId(): string {
    const context = Shopware.Context.api as CrudApiContext;

    return context.systemLanguageId ?? context.languageId ?? '';
}

export function getPropertyError(entity: CrudEntity | null, property: string): CrudPropertyError | null {
    if (!entity) {
        return null;
    }

    const host = Shopware as unknown as CompatibilityHost;
    if (host.Store) {
        return host.Store.get('error').getApiError(entity, property);
    }

    return host.State?.getters?.['error/getApiError']?.(entity, property) ?? null;
}

export function removePropertyError(entity: CrudEntity, property: string): void {
    const dalEntity = entity as DalEntity;
    const entityName = dalEntity.getEntityName?.();
    if (!entityName) {
        return;
    }

    const expression = `${entityName}.${entity.id}.${property}`;
    const host = Shopware as unknown as CompatibilityHost;
    if (host.Store) {
        host.Store.get('error').removeApiError(expression);
        return;
    }

    void host.State?.dispatch?.('error/removeApiError', expression);
}

export function propertyErrorMessage(error: CrudPropertyError | null): string {
    return error?.detail?.trim() || error?.code?.trim() || '';
}
