import type { CmsElementApi, CmsElementDefinition, CmsElementRegistration } from '../types';
import { validateCmsElementDefinition } from './validation';

export type CmsElementRegistrar = (definition: Readonly<CmsElementDefinition>) => void;

function cloneAndFreeze<T>(value: T): T {
    if (Array.isArray(value)) {
        return Object.freeze(value.map((item: unknown) => cloneAndFreeze<unknown>(item))) as T;
    }

    if (value !== null && typeof value === 'object') {
        if (Object.getPrototypeOf(value) === Object.prototype || Object.getPrototypeOf(value) === null) {
            const copy: Record<string, unknown> = {};
            Object.entries(value as Record<string, unknown>).forEach(
                ([
                    key,
                    item,
                ]) => {
                    copy[key] = cloneAndFreeze<unknown>(item);
                },
            );

            return Object.freeze(copy) as T;
        }
    }

    return value;
}

export class CmsElementRegistry implements CmsElementApi {
    private readonly definitions = new Map<string, Readonly<CmsElementDefinition>>();

    public constructor(private readonly registrar: CmsElementRegistrar) {}

    public register(definition: CmsElementDefinition): CmsElementRegistration {
        validateCmsElementDefinition(definition);

        if (this.definitions.has(definition.name)) {
            throw new Error(`[FroshJetpack Admin CMS] ${definition.name}: registration already exists`);
        }

        const snapshot = cloneAndFreeze(definition);
        this.registrar(snapshot);
        this.definitions.set(snapshot.name, snapshot);

        return Object.freeze({ name: snapshot.name });
    }

    public get(name: string): Readonly<CmsElementDefinition> | undefined {
        return this.definitions.get(name);
    }

    public all(): readonly Readonly<CmsElementDefinition>[] {
        return Object.freeze(Array.from(this.definitions.values()));
    }
}
