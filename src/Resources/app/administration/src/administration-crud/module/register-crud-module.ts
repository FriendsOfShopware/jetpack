import { associationReadPrivileges } from '../association/association';
import type { CrudRole, ResolvedCrudDefinition } from '../types';

type ModuleRoute = {
    params: Record<string, string | string[]>;
};

type ShopwareModuleConfig = Parameters<typeof Shopware.Module.register>[1];
type ShopwareRouteConfig = NonNullable<ShopwareModuleConfig['routes']>[string];

function unique(values: string[]): string[] {
    return Array.from(new Set(values));
}

function actionPrivileges(definition: ResolvedCrudDefinition, role: CrudRole): string[] {
    const actions = [
        ...(definition.listing === false ? [] : (definition.listing.rowActions ?? [])),
        ...(definition.listing === false || definition.listing.bulk === false
            ? []
            : (definition.listing.bulk?.actions ?? [])),
        ...(definition.detail === false ? [] : (definition.detail.actions ?? [])),
    ];

    return actions.flatMap((action) =>
        action.privilege && (action.requiredRole ?? 'editor') === role ? [action.privilege] : [],
    );
}

export function registerCrudPrivileges(definition: ResolvedCrudDefinition): void {
    const key = definition.acl.key;
    const additional = definition.acl.additional ?? {};
    const associationReads = associationReadPrivileges(definition);

    Shopware.Service('privileges').addPrivilegeMappingEntry({
        category: definition.acl.category ?? 'additional_permissions',
        parent: definition.acl.parent ?? null,
        key,
        roles: {
            viewer: {
                privileges: unique([
                    `${definition.entity}:read`,
                    ...associationReads,
                    ...actionPrivileges(definition, 'viewer'),
                    ...(additional.viewer ?? []),
                ]),
                dependencies: [],
            },
            creator: {
                privileges: unique([
                    `${definition.entity}:create`,
                    ...actionPrivileges(definition, 'creator'),
                    ...(additional.creator ?? []),
                ]),
                dependencies: [`${key}.viewer`],
            },
            editor: {
                privileges: unique([
                    `${definition.entity}:update`,
                    ...actionPrivileges(definition, 'editor'),
                    ...(additional.editor ?? []),
                ]),
                dependencies: [`${key}.viewer`],
            },
            deleter: {
                privileges: unique([
                    `${definition.entity}:delete`,
                    ...actionPrivileges(definition, 'deleter'),
                    ...(additional.deleter ?? []),
                ]),
                dependencies: [`${key}.viewer`],
            },
        },
    });
}

export function registerCrudModule(definition: ResolvedCrudDefinition): void {
    registerCrudPrivileges(definition);

    const routes: Record<string, ShopwareRouteConfig> = {};

    if (definition.listing !== false) {
        routes.list = {
            component: 'frosh-jetpack-crud-list',
            path: 'list',
            props: {
                default: () => ({ crudId: definition.id }),
            },
            meta: {
                privilege: `${definition.acl.key}.viewer`,
            },
        };
    }

    if (definition.detail !== false && definition.detail.create !== false) {
        routes.create = {
            component: 'frosh-jetpack-crud-detail',
            path: 'create',
            props: {
                default: () => ({
                    crudId: definition.id,
                    entityId: null,
                }),
            },
            meta: {
                parentPath: definition.routeNames.list,
                privilege: `${definition.acl.key}.creator`,
            },
        };
    }

    if (definition.detail !== false) {
        routes.detail = {
            component: 'frosh-jetpack-crud-detail',
            path: 'detail/:id',
            props: {
                default: (route: ModuleRoute) => ({
                    crudId: definition.id,
                    entityId: String(route.params.id),
                }),
            },
            meta: {
                parentPath: definition.routeNames.list,
                privilege: `${definition.acl.key}.viewer`,
            },
        };
    }

    const navigationConfig = definition.module.navigation;
    const navigation =
        definition.listing !== false && navigationConfig
            ? [
                  {
                      id: `${definition.id}.navigation`,
                      label: definition.module.titleSnippet,
                      color: definition.module.color ?? '#0870ff',
                      path: definition.routeNames.list as string,
                      parent: navigationConfig.parent,
                      icon: definition.module.icon ?? 'regular-folder-open',
                      position: navigationConfig.position ?? 100,
                      privilege: `${definition.acl.key}.viewer`,
                  },
              ]
            : [];

    const settingsConfig = definition.module.settings;
    const settingsItem =
        definition.listing !== false && settingsConfig
            ? {
                  group: settingsConfig.group ?? 'plugins',
                  to: definition.routeNames.list as string,
                  icon: definition.module.icon ?? 'regular-folder-open',
                  name: definition.module.titleSnippet,
                  label: definition.module.titleSnippet,
                  position: settingsConfig.position ?? 100,
                  privilege: `${definition.acl.key}.viewer`,
              }
            : undefined;

    Shopware.Module.register(definition.moduleName, {
        type: 'plugin',
        name: definition.moduleName,
        title: definition.module.titleSnippet,
        description: definition.module.descriptionSnippet ?? definition.module.titleSnippet,
        color: definition.module.color ?? '#0870ff',
        icon: definition.module.icon ?? 'regular-folder-open',
        entity: definition.entity,
        routes,
        navigation,
        ...(settingsItem ? { settingsItem } : {}),
    });
}
