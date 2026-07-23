import { buildOperations } from './scope-state';
import type { ValueState } from '../../../../types';

const address = {
    salesChannelId: 'sales-channel',
    languageId: 'language',
};

function state(value: unknown, overridden: boolean): ValueState {
    return {
        value,
        overridden,
        inheritedValue: 'inherited',
        inheritedSource: {
            salesChannelId: null,
            languageId: null,
            default: true,
        },
        address,
    };
}

describe('scope-state', () => {
    const isEqual = (left: unknown, right: unknown) => JSON.stringify(left) === JSON.stringify(right);

    it('writes newly enabled and changed overrides', () => {
        const result = buildOperations(
            {
                enabled: state(false, true),
                features: state(['export'], true),
            },
            {
                enabled: state(true, false),
                features: state(['search'], true),
            },
            isEqual,
        );

        expect(result).toEqual({
            writes: [
                { key: 'enabled', ...address, value: false },
                { key: 'features', ...address, value: ['export'] },
            ],
            deletes: [],
        });
    });

    it('deletes a disabled override and ignores unchanged values', () => {
        const result = buildOperations(
            {
                headline: state('Inherited', false),
                retries: state(3, true),
            },
            {
                headline: state('Custom', true),
                retries: state(3, true),
            },
            isEqual,
        );

        expect(result).toEqual({
            writes: [],
            deletes: [{ key: 'headline', ...address }],
        });
    });
});
