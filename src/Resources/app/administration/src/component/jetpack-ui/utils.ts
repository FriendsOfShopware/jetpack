import type { JetpackIconPath } from './types';

let nextId = 0;

export function createJetpackId(prefix: string): string {
    nextId += 1;

    return `jetpack-${prefix}-${nextId}`;
}

export function eventValue(event: Event): string {
    return (event.target as HTMLInputElement).value;
}

export function eventChecked(event: Event): boolean {
    return (event.target as HTMLInputElement).checked;
}

export function clamp(value: number, minimum?: number | null, maximum?: number | null): number {
    return Math.min(maximum ?? Number.POSITIVE_INFINITY, Math.max(minimum ?? Number.NEGATIVE_INFINITY, value));
}

export function getByPath(value: unknown, path: string): unknown {
    return path.split('.').reduce<unknown>((current, part) => {
        if (current === null || typeof current !== 'object') {
            return undefined;
        }

        return (current as Record<string, unknown>)[part];
    }, value);
}

export const jetpackIcons: Record<string, JetpackIconPath[]> = {
    check: [{ d: 'M5 12.5 9.25 17 19 7' }],
    close: [{ d: 'm6 6 12 12M18 6 6 18' }],
    plus: [{ d: 'M12 5v14M5 12h14' }],
    minus: [{ d: 'M5 12h14' }],
    'chevron-down': [{ d: 'm6 9 6 6 6-6' }],
    'chevron-up': [{ d: 'm6 15 6-6 6 6' }],
    'chevron-left': [{ d: 'm15 6-6 6 6 6' }],
    'chevron-right': [{ d: 'm9 6 6 6-6 6' }],
    search: [
        { d: 'm20 20-4.5-4.5M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z' },
    ],
    info: [{ d: 'M12 10v7M12 7h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' }],
    warning: [{ d: 'M12 4 3 20h18L12 4ZM12 9v5M12 17h.01' }],
    critical: [{ d: 'M12 8v5M12 16h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' }],
    eye: [
        {
            d: 'M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6S2.5 12 2.5 12ZM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
        },
    ],
    'eye-off': [
        {
            d: 'm3 3 18 18M10.6 6.1A10.8 10.8 0 0 1 12 6c6 0 9.5 6 9.5 6a14 14 0 0 1-2.1 2.7M6.2 6.2C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6a9.7 9.7 0 0 0 3-.5M9.9 9.9a3 3 0 0 0 4.2 4.2',
        },
    ],
    trash: [{ d: 'M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5' }],
    edit: [{ d: 'm4 20 4.5-1 10-10a2.1 2.1 0 0 0-3-3l-10 10L4 20ZM14 7l3 3' }],
    more: [{ d: 'M5 12h.01M12 12h.01M19 12h.01' }],
    calendar: [
        {
            d: 'M5 4v3M19 4v3M4 9h16M5 6h14a1 1 0 0 1 1 1v13H4V7a1 1 0 0 1 1-1Z',
        },
    ],
    upload: [{ d: 'M12 16V4M7 9l5-5 5 5M5 15v5h14v-5' }],
    download: [{ d: 'M12 4v12M7 11l5 5 5-5M5 20h14' }],
};
