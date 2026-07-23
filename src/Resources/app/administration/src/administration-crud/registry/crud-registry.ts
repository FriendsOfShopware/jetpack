import type {
    CrudApi,
    CrudDefinition,
    CrudEntity,
    CrudExtension,
    CrudExtensionRegistration,
    CrudRegistration,
    ResolvedCrudDefinition,
} from '../types';
import { applyCrudExtensions } from './extension-resolver';
import { validateCrudDefinition, validateCrudExtension } from './validation';

export type CrudModuleRegistrar = (definition: ResolvedCrudDefinition) => void;
export type CrudPrivilegeRegistrar = (definition: ResolvedCrudDefinition) => void;

function cloneAndFreeze<T>(value: T): T {
    if (Array.isArray(value)) {
        const items = (value as unknown[]).map((item: unknown) => cloneAndFreeze<unknown>(item));

        return Object.freeze(items) as T;
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

function resolveDefinition<TEntity extends CrudEntity>(
    definition: CrudDefinition<TEntity>,
): ResolvedCrudDefinition<TEntity> {
    const moduleName = definition.id.replaceAll('.', '-');
    const routeNames = {
        ...(definition.listing !== false ? { list: `${definition.id}.list` } : {}),
        ...(definition.detail !== false && definition.detail.create !== false ? { create: `${definition.id}.create` } : {}),
        ...(definition.detail !== false ? { detail: `${definition.id}.detail` } : {}),
    };

    return cloneAndFreeze({
        ...definition,
        moduleName,
        routeNames,
    });
}

export class CrudRegistry implements CrudApi {
    private readonly definitionSources = new Map<string, Readonly<CrudDefinition>>();

    private readonly resolvedDefinitions = new Map<string, Readonly<ResolvedCrudDefinition>>();

    private readonly extensions = new Map<string, Array<Readonly<CrudExtension>>>();

    private readonly extensionTargets = new Map<string, string>();

    public constructor(
        private readonly moduleRegistrar: CrudModuleRegistrar,
        private readonly privilegeRegistrar: CrudPrivilegeRegistrar = () => {},
    ) {}

    public register<TEntity extends CrudEntity>(definition: CrudDefinition<TEntity>): CrudRegistration {
        validateCrudDefinition(definition);

        if (this.definitionSources.has(definition.id)) {
            throw new Error(`[FroshJetpack Admin CRUD] ${definition.id}: registration already exists`);
        }

        const source = cloneAndFreeze(definition);
        const extensions = (this.extensions.get(definition.id) ?? []) as Array<CrudExtension<TEntity>>;
        const resolved = resolveDefinition(applyCrudExtensions(source, extensions));
        this.moduleRegistrar(resolved as ResolvedCrudDefinition);
        this.definitionSources.set(definition.id, source as CrudDefinition);
        this.resolvedDefinitions.set(definition.id, resolved as ResolvedCrudDefinition);

        return Object.freeze({
            id: resolved.id,
            moduleName: resolved.moduleName,
            routeNames: resolved.routeNames,
        });
    }

    public extend<TEntity extends CrudEntity>(target: string, extension: CrudExtension<TEntity>): CrudExtensionRegistration {
        validateCrudExtension(target, extension);

        const existingTarget = this.extensionTargets.get(extension.id);
        if (existingTarget) {
            throw new Error(`[FroshJetpack Admin CRUD] ${extension.id}: extension already targets "${existingTarget}"`);
        }

        const snapshot = cloneAndFreeze(extension);
        const nextExtensions = [
            ...(this.extensions.get(target) ?? []),
            snapshot as CrudExtension,
        ];
        const definition = this.definitionSources.get(target);

        if (definition) {
            const resolved = resolveDefinition(
                applyCrudExtensions(definition as CrudDefinition<TEntity>, nextExtensions as Array<CrudExtension<TEntity>>),
            );
            this.privilegeRegistrar(resolved as ResolvedCrudDefinition);
            this.resolvedDefinitions.set(target, resolved as ResolvedCrudDefinition);
        }

        this.extensions.set(target, nextExtensions);
        this.extensionTargets.set(extension.id, target);

        return Object.freeze({
            id: extension.id,
            target,
        });
    }

    public get(id: string): Readonly<ResolvedCrudDefinition> | undefined {
        return this.resolvedDefinitions.get(id);
    }

    public all(): readonly Readonly<ResolvedCrudDefinition>[] {
        return Object.freeze(Array.from(this.resolvedDefinitions.values()));
    }
}
