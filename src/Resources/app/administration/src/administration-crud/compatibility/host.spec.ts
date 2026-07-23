import {
    createApiContext,
    getPropertyError,
    initialLanguageId,
    propertyErrorMessage,
    removePropertyError,
    systemLanguageId,
} from './host';
import type { CrudEntity } from '../types';

type MutableHost = {
    Store?: unknown;
    State?: unknown;
};

describe('CRUD Shopware compatibility', () => {
    const host = Shopware as unknown as MutableHost;
    const entity: CrudEntity & { getEntityName: () => string } = {
        id: 'review-1',
        getEntityName: () => 'acme_review',
    };

    afterEach(() => {
        delete host.Store;
        delete host.State;
    });

    it('creates an isolated API context for a translated request', () => {
        Object.assign(Shopware.Context.api, {
            languageId: 'default-language',
            systemLanguageId: 'system-language',
            versionId: 'live',
        });

        expect(createApiContext('translated-language')).toEqual(
            expect.objectContaining({
                languageId: 'translated-language',
                systemLanguageId: 'system-language',
                versionId: 'live',
            }),
        );
        expect(Shopware.Context.api.languageId).toBe('default-language');
    });

    it('exposes current and system language IDs without changing the host context', () => {
        expect(initialLanguageId()).toBe('default-language');
        expect(systemLanguageId()).toBe('system-language');
    });

    it('reads and removes property errors through the 6.7 store API', () => {
        const removeApiError = jest.fn();
        host.Store = {
            get: () => ({
                getApiError: () => ({ code: 'FRAMEWORK__REQUIRED_FIELD_MISSING', detail: 'This value is required.' }),
                removeApiError,
            }),
        };

        const error = getPropertyError(entity, 'title');
        removePropertyError(entity, 'title');

        expect(propertyErrorMessage(error)).toBe('This value is required.');
        expect(removeApiError).toHaveBeenCalledWith('acme_review.review-1.title');
    });

    it('falls back to the 6.6 Vuex error API', () => {
        const dispatch = jest.fn();
        host.State = {
            getters: {
                'error/getApiError': () => ({ code: 'ACME__INVALID' }),
            },
            dispatch,
        };

        expect(propertyErrorMessage(getPropertyError(entity, 'title'))).toBe('ACME__INVALID');
        removePropertyError(entity, 'title');
        expect(dispatch).toHaveBeenCalledWith('error/removeApiError', 'acme_review.review-1.title');
    });
});
