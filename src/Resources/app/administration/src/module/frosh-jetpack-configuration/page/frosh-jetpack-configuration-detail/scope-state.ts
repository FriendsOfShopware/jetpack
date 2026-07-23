import type { ConfigurationOperation, ValueState } from '../../../../types';

export function buildOperations(
    values: Record<string, ValueState>,
    original: Record<string, ValueState>,
    isEqual: (left: unknown, right: unknown) => boolean,
): { writes: ConfigurationOperation[]; deletes: ConfigurationOperation[] } {
    const writes: ConfigurationOperation[] = [];
    const deletes: ConfigurationOperation[] = [];

    Object.entries(values).forEach(
        ([
            key,
            entry,
        ]) => {
            const originalEntry = original[key];
            if (!originalEntry) {
                return;
            }

            if (entry.overridden && (!originalEntry.overridden || !isEqual(entry.value, originalEntry.value))) {
                writes.push({ key, ...entry.address, value: entry.value });
            } else if (!entry.overridden && originalEntry.overridden) {
                deletes.push({ key, ...entry.address });
            }
        },
    );

    return { writes, deletes };
}
