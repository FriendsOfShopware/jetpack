import { registerCrudModule, registerCrudPrivileges } from './module/register-crud-module';
import { CrudRegistry } from './registry/crud-registry';
import type { FroshJetpackGlobal } from './types';

const API_VERSION = 1 as const;
const RUNTIME_VERSION = '0.3.0';

export function installFroshJetpackGlobal(): FroshJetpackGlobal {
    const existing = (
        globalThis as unknown as {
            FroshJetpack?: Omit<FroshJetpackGlobal, 'apiVersion'> & {
                apiVersion: number;
            };
        }
    ).FroshJetpack;
    if (existing) {
        if (existing.apiVersion !== API_VERSION) {
            throw new Error(
                `[FroshJetpack] Global API ${existing.apiVersion} is incompatible with runtime API ${API_VERSION}`,
            );
        }

        return existing as FroshJetpackGlobal;
    }

    const registry = new CrudRegistry(registerCrudModule, registerCrudPrivileges);
    const runtime: FroshJetpackGlobal = Object.freeze({
        apiVersion: API_VERSION,
        runtimeVersion: RUNTIME_VERSION,
        Admin: Object.freeze({
            Crud: registry,
        }),
    });

    Object.defineProperty(globalThis, 'FroshJetpack', {
        value: runtime,
        configurable: false,
        enumerable: true,
        writable: false,
    });

    return runtime;
}
