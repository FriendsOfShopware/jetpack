# Administration CRUD

> Status: Implemented. The global registry, cross-plugin extensions, listing/detail actions,
> translated DAL contexts, bulk operations, DAL property errors, and the complete first renderer set
> are available.

Frosh Jetpack provides a declarative CRUD module runtime for Shopware Administration extensions.
It ports the useful ideas from the earlier `@friendsofshopware/jetpack` prototype into the central
Frosh Jetpack plugin, while replacing its copied runtime, host-component wrappers, and three-step
registration API.

The runtime targets Shopware 6.6 and 6.7. It renders the existing independent `jetpack-*` component
catalog and uses only stable Shopware boundaries for DAL repositories, Criteria, routing, snippets,
ACL, and the outer `sw-page` shell.

## Design goals

- One Frosh Jetpack Administration runtime is shared by every consuming plugin.
- Consumers register a complete CRUD module through one type-safe call.
- The global runtime is ready before Shopware loads normal Administration plugin modules.
- IDs, route names, fields, columns, hooks, and extension points have deterministic contracts.
- A consumer can extend another registered CRUD module without mutating its definition.
- The generated listing and detail pages work with any registered Shopware DAL entity, not only an
  entity declared through Jetpack's PHP attributes.
- No Meteor, `sw-*` form control, legacy mixin, Vuex, or Pinia contract is public API.

The first version is not a visual page builder, a replacement DAL, or an automatic UI inferred from
every entity field. The definition remains explicit and supports custom registered components where
the built-in field and cell types are insufficient.

## Current implementation

The runtime currently provides:

- `FroshJetpack.Admin.Crud.register()`, `extend()`, `get()`, and `all()` on the early-loaded global;
- atomic module, route, navigation/settings, and ACL-role registration;
- repository-backed search, sorting, pagination, create, edit, save/reload, and delete flows;
- Criteria callbacks plus explicit and inferred listing/detail association loading;
- automatic `:read` privileges for declared association target entities;
- scalar, date, color, rich-text, static select, custom, to-one `entity-select`, and to-many
  `entity-multi-select` fields;
- text, number, currency, boolean, date, badge, and custom listing cells;
- ACL-aware row, detail, bulk, and bulk-delete actions;
- opt-in language switching with isolated translated API contexts;
- DAL property errors through a private Vuex/Pinia compatibility adapter;
- deterministic additive cross-plugin columns, cards, fields, and actions;
- idempotent early bootstrapping across the 6.6 classic/Vite and 6.7 Vite Administration hosts.

## Global API

The public runtime is an independent global named `FroshJetpack`. It is not attached to `Shopware`,
which avoids claiming an official Shopware namespace and protects against a future host collision.
Its first public surface is deliberately small:

```ts
interface FroshJetpackGlobal {
    readonly apiVersion: 1;
    readonly runtimeVersion: string;
    readonly Admin: {
        readonly Crud: {
            register<TEntity extends Record<string, unknown>>(definition: CrudDefinition<TEntity>): CrudRegistration;
            extend<TEntity extends Record<string, unknown>>(
                target: string,
                extension: CrudExtension<TEntity>,
            ): CrudExtensionRegistration;
            get(id: string): Readonly<ResolvedCrudDefinition> | undefined;
            all(): readonly Readonly<ResolvedCrudDefinition>[];
        };
        readonly Cms: CmsElementApi;
    };
}
```

Definitions and extensions may be registered in either order. `get()` and `all()` expose the current
immutable resolved views. Internal adapters such as state access, component registration, Criteria
construction, and property-error mapping are not exposed, so they can change between Shopware 6.6
and 6.7 without changing consumer code.

`Admin.Cms` shares the same early runtime but has its own focused contract. See
[declarative CMS elements](../administration-cms-elements/README.md).

### Guaranteed early bootstrap

Shopware normally injects Administration plugin bundles asynchronously and, in the relevant paths,
loads them in parallel. A Composer dependency alone therefore does not establish JavaScript order.
Frosh Jetpack establishes that order at the HTML boot boundary instead.

The plugin adds `Resources/views/administration/index.html.twig`, uses `sw_extends` for Shopware's
original template, and overrides the smallest available boot blocks.
`FroshJetpack::getTemplatePriority()` returns a dedicated negative priority so this template is the
outer wrapper in the Shopware template hierarchy. It still calls `parent()` so other template
extensions remain in the inheritance chain. No Shopware page markup or start configuration is copied
into Jetpack.

The shipped Administration entry is an ESM build on every supported host. Jetpack reads its own Vite
entrypoints manifest through a private Twig helper instead of referencing Shopware's Vite Twig
functions, which did not exist in the first 6.6 minors. The bootstrap then uses two version-aware
template paths:

1. On the Shopware 6.6 classic path, the Jetpack entry is inserted through
   `administration_templates`, the only stable hook available across all 6.6 minors. A temporary
   `window.Shopware` setter intercepts core's assignment, restores the normal writable property, and
   guards `Shopware.Application.start()` before core can invoke it.
2. On the Shopware 6.6 Vite and Shopware 6.7 Vite paths, the Frosh Jetpack entry is rendered for the
   `FroshJetpack` entrypoint with the current CSP nonce. A small nonced bootstrap wraps
   `window.startApplication` and lets the original function continue only after the Jetpack entry's
   load promise resolves.
3. Shopware can then initialize and inject all normal plugin bundles. Every consumer observes the
   fully initialized `globalThis.FroshJetpack`, independently of normal plugin bundle order.
4. Failure to load Jetpack rejects the guarded start with a visible diagnostic. It must never degrade
   into a partially booted Administration with a missing global.

The Administration bundle list still contains Frosh Jetpack. The entry must therefore be idempotent.
A symbol stored through `Symbol.for()` protects the bootstrap across a second evaluation, while
native module caching provides another guard. Re-entry verifies the API major and returns without
registering components, routes, or services twice.

The template path and hook are shared by Shopware 6.6 and 6.7, but the exact asset mode is hidden in
Jetpack's Twig compatibility service. Automated tests must cover legacy Webpack, 6.6 Vite, and 6.7
Vite rendering rather than using version checks in consumer code.

Consuming plugins must declare `frosh/jetpack` in Composer. The Composer constraint is the runtime
compatibility boundary. A standalone consumer typings package is not published yet, so do not add an
`@friendsofshopware/jetpack-admin-api` dependency. Until typings are shipped, projects that require
compile-time checking can keep a project-local ambient declaration for the API version they consume.

## Consumer API

```ts
type ReviewAdminEntity = {
    id: string;
    title: string;
    active: boolean;
    rating: number | null;
    productId: string | null;
    createdAt: string;
};

FroshJetpack.Admin.Crud.register<ReviewAdminEntity>({
    apiVersion: 1,
    id: 'acme.review',
    entity: 'acme_review',
    snippets: 'acme-review',

    module: {
        titleSnippet: 'acme-review.general.title',
        descriptionSnippet: 'acme-review.general.description',
        icon: 'regular-star',
        color: '#0870ff',
        navigation: {
            parent: 'sw-catalogue',
            position: 100,
        },
        settings: false,
    },

    acl: {
        key: 'acme_review',
        category: 'permissions',
        parent: 'catalogues',
        additional: {
            viewer: ['product:read'],
        },
    },

    listing: {
        search: true,
        pageSize: 25,
        defaultSort: [{ property: 'createdAt', direction: 'DESC' }],
        criteria({ criteria }) {
            criteria.addAssociation('product');
        },
        columns: [
            {
                id: 'title',
                property: 'title',
                linkToDetail: true,
                sortable: true,
            },
            {
                id: 'active',
                property: 'active',
                type: 'boolean',
                align: 'center',
            },
            {
                id: 'rating',
                property: 'rating',
                type: 'number',
                align: 'end',
            },
            {
                id: 'product',
                property: 'product.name',
                association: 'product',
                associationEntity: 'product',
            },
        ],
        rowActions: [
            {
                id: 'open-product',
                labelSnippet: 'acme-review.list.actions.openProduct',
                requiredRole: 'viewer',
                async handler({ entity, router }) {
                    await router.push({ name: 'sw.product.detail', params: { id: entity.productId } });
                },
            },
        ],
        bulk: {
            delete: true,
            actions: [
                {
                    id: 'publish',
                    labelSnippet: 'acme-review.list.actions.publish',
                    confirmation: {
                        titleSnippet: 'acme-review.list.publish.title',
                        messageSnippet: 'acme-review.list.publish.message',
                    },
                    async handler({ selectedIds, apiContext, reload }) {
                        await publishReviews(selectedIds, apiContext);
                        await reload();
                    },
                },
            ],
        },
    },

    detail: {
        labelProperty: 'title',
        cards: [
            {
                id: 'general',
                fields: [
                    { id: 'title', property: 'title', type: 'text' },
                    { id: 'active', property: 'active', type: 'switch' },
                    { id: 'rating', property: 'rating', type: 'number' },
                    {
                        id: 'product',
                        property: 'productId',
                        type: 'entity-select',
                        entity: 'product',
                    },
                    {
                        id: 'tags',
                        property: 'tags',
                        type: 'entity-multi-select',
                        entity: 'tag',
                    },
                ],
            },
        ],
        actions: [
            {
                id: 'publish',
                labelSnippet: 'acme-review.detail.actions.publish',
                async handler({ entity, apiContext, reload }) {
                    await publishReviews([entity.id], apiContext);
                    await reload();
                },
            },
        ],
    },

    translation: { enabled: true },

    hooks: {
        beforeSave({ entity, mode }) {
            if (mode === 'create') {
                entity.active = true;
            }
        },
    },
});
```

`id` is the globally unique registration and route prefix. It must be explicitly namespaced by the
consumer. The entity name alone is not sufficient because route and module ownership belong to a
plugin, not to Jetpack.

The generated route names are predictable. A route is omitted when its corresponding page or create
operation is disabled:

| Operation | Route                |
| --------- | -------------------- |
| Listing   | `acme.review.list`   |
| Create    | `acme.review.create` |
| Detail    | `acme.review.detail` |

The internal Shopware module and generic page component names are implementation details. Consumers
receive the route names through the returned `CrudRegistration` as well as through the deterministic
convention.

## Definition contracts

### Module placement

`module.navigation` and `module.settings` are either a configuration object or `false`. This avoids
the ambiguous `showInNavigation`/`showInSettings` booleans and false-value default bugs in the old
implementation. Defaults use nullish semantics; an explicit `false`, `0`, or empty string is never
silently replaced.

Navigation, settings entries, and all generated routes use the CRUD viewer/creator/editor/deleter ACL
roles. Visible labels are snippet keys. No hard-coded user-facing fallback is added by the runtime.

By default, an ACL key of `acme_review` generates the conventional mapping:

| Role                  | Privileges and dependencies                          |
| --------------------- | ---------------------------------------------------- |
| `acme_review.viewer`  | `acme_review:read` plus configured viewer privileges |
| `acme_review.creator` | `acme_review:create`, depends on viewer              |
| `acme_review.editor`  | `acme_review:update`, depends on viewer              |
| `acme_review.deleter` | `acme_review:delete`, depends on viewer              |

`listing` and `detail` accept either a definition or `false`; at least one page must be enabled. A
read-only listing therefore does not need to register unused create and detail routes.

### Listing

Columns are arrays because order is part of the UI contract. Every column has a stable `id` and a
typed `property`. The first release supports:

- search, pagination, sorting, empty, loading, and error states;
- configurable Criteria associations and filters;
- create, edit, and delete actions with ACL checks;
- ACL-aware custom row actions;
- row selection, optional bulk deletion, and custom bulk actions with optional confirmation;
- built-in text, number, currency, boolean, date, and badge cell renderers;
- a custom registered component escape hatch for specialized cells;
- language switching when `translation: { enabled: true }` is declared.

Default column snippets resolve to
`<snippets>.list.columns.<column-id>`. `labelSnippet` overrides that convention.

Inline editing is deferred until its save, validation, and error behavior can be specified without
depending on `sw-entity-listing` internals.

### Detail and create

Cards and fields are ordered arrays with stable IDs. Built-in field types map only to Frosh Jetpack
components:

| Field type                                               | Component                                                      |
| -------------------------------------------------------- | -------------------------------------------------------------- |
| `text`, `email`, `url`, `password`, `number`, `textarea` | Corresponding `jetpack-*-field` or `jetpack-textarea`          |
| `checkbox`, `switch`                                     | `jetpack-checkbox`, `jetpack-switch`                           |
| `select`, `multi-select`                                 | `jetpack-select`, `jetpack-multi-select`                       |
| `entity-select`                                          | `jetpack-entity-select`                                        |
| `entity-multi-select`                                    | `jetpack-entity-multi-select`                                  |
| `date`, `color`, `rich-text`                             | Corresponding specialized Jetpack control                      |
| `custom`                                                 | Consumer-registered component using the Jetpack field contract |

Static select options are ordered arrays of `{ value, labelSnippet, disabled? }`; the old reversed
label/value record is not retained. Entity selectors accept a Criteria callback.

`entity-select` binds a foreign-key property such as `productId`. `entity-multi-select` binds an
association property containing Shopware's `EntityCollection`, for example `tags`. Jetpack loads the
to-many association in the detail Criteria and updates the existing collection through its
`add()`/`remove()` contract so repository change tracking can persist the relationship. Listing
columns infer `product` from `product.name`; use `association` for a different or deeper path and
`associationEntity` to add the target entity's read privilege automatically.

Custom field components receive `modelValue`, `entity`, `field`, `disabled`, and `error`, and must emit
`update:modelValue`. This preserves one value contract across built-in and consumer fields.

`required` and `disabled` are explicit field settings. Server-side DAL write errors are resolved by
entity and property and passed to the matching Jetpack control. Editing a property removes its stale
error through the Shopware 6.6 Vuex or Shopware 6.7 store adapter. After a successful save, Jetpack
reloads the entity so DAL origin state and change tracking are synchronized.

### Translations

Translation support is opt-in because not every entity has a translation definition:

```ts
translation: {
    enabled: true,
    languageLabelProperty: 'name',
}
```

The listing and existing-record detail page show a language selector. Every repository request,
association selector, hook, and action receives a cloned API context containing that language ID;
the shared `Shopware.Context.api` object is never mutated. Create mode uses the system language and
disables language switching until the first save.

### Actions

Row, detail, and bulk handlers receive the resolved definition, repository, cloned API context,
router, and `reload()`. Row/detail handlers additionally receive the current entity; bulk handlers
receive immutable selected IDs and the selected entities loaded on the current page. An action uses
the CRUD editor role by default. Set `requiredRole` to another generated role or `privilege` to an
explicit ACL privilege. Explicit action privileges are added to the selected role's privilege
mapping during registration.

### Hooks

Hooks receive an explicit context object rather than a Vue component instance:

```ts
type CrudHooks<TEntity> = {
    beforeLoad?: (context: LoadContext<TEntity>) => void | Promise<void>;
    afterLoad?: (context: LoadedContext<TEntity>) => void | Promise<void>;
    beforeSave?: (context: SaveContext<TEntity>) => void | Promise<void>;
    afterSave?: (context: SavedContext<TEntity>) => void | Promise<void>;
    onError?: (context: ErrorContext<TEntity>) => void;
};
```

The context exposes the repository, entity, mode, API context, router, and a reload callback where
relevant. It does not expose private page state. Save order is `beforeSave`, repository save, entity
reload, then `afterSave`. A thrown hook error stops the operation, keeps the form dirty, and goes
through the same error-notification path as repository failures.

## Cross-plugin extensions

Another plugin can add UI to an existing CRUD registration without overriding its definition:

```ts
FroshJetpack.Admin.Crud.extend('acme.review', {
    apiVersion: 1,
    id: 'analytics.review-extension',
    detail: {
        cards: [
            {
                id: 'analytics',
                after: 'general',
                fields: [
                    {
                        id: 'score',
                        property: 'rating',
                        type: 'custom',
                        component: 'analytics-review-score',
                    },
                ],
            },
        ],
        fields: [
            {
                id: 'analytics-note',
                card: 'general',
                after: 'title',
                property: 'title',
                type: 'custom',
                component: 'analytics-review-note',
            },
        ],
    },
});
```

Extensions are additive in API version 1. They may add columns, cards, fields, row actions, bulk
actions, and detail actions. They cannot replace the entity, route ownership, repository, or ACL
root. An explicit `listing: false`, `detail: false`, or `listing.bulk: false` remains an owner opt-out
and cannot be overridden by an extension. Ordering uses stable `before`/`after` IDs with a numeric
position as a fallback. Duplicate extension or element IDs are errors and include both owners in the
diagnostic.

Definitions and extensions may arrive in either order. The registry stores both and resolves an
immutable view whenever a generic page mounts. `get()` and `all()` return read-only resolved views for
debugging and tooling, never mutable registry state.

## Internal architecture

The feature is split into these internal areas:

```text
administration-crud/
├── install-global   global contract and idempotent bootstrap
├── registry/        validation, normalization, duplicate checks, extensions
├── compatibility/   Shopware 6.6/6.7 state and entity-definition adapters
├── module/          Shopware module, route, navigation, settings, ACL registration
├── listing/         generic listing page, actions, bulk operations, cell resolution
├── detail/          generic create/detail page, actions, errors, field resolution
└── association/     Criteria association inference and application
```

Only the exported types and installed global are public. The compatibility layer is the sole place
allowed to probe Vuex/Pinia or other version-sensitive host behavior.

The runtime registers one generic listing component and one generic detail component. Individual CRUD
definitions do not generate copies of Vue component options. Their routes pass the registration ID to
the generic page, which resolves the current immutable definition from the registry. This also allows
late additive extensions without re-registering Shopware components.

Pages use injected services rather than Shopware's legacy listing, notification, and placeholder
mixins. The outer Administration page may use `sw-page`; all controls, layouts, tables,
menus, modals, fields, loaders, and feedback use the independent `jetpack-*` catalog.

## What is ported and what is replaced

| Prototype concept                                    | Frosh Jetpack design                        |
| ---------------------------------------------------- | ------------------------------------------- |
| Three calls for listing, detail, and module          | One atomic `Crud.register()` definition     |
| Runtime bundled into every plugin                    | One early global runtime                    |
| Version-stamped wrapper component names              | Canonical Frosh Jetpack component catalog   |
| `sw-*`/`mt-*` control resolution                     | Independent `jetpack-*` controls            |
| Entity-derived module ownership                      | Explicit globally unique registration ID    |
| Object records for columns and fields                | Ordered arrays with stable IDs              |
| Public state, mixin, Criteria, and component facades | Private compatibility layer                 |
| Vue component instance passed implicitly             | Explicit typed lifecycle contexts           |
| Host `sw-entity-listing` behavior                    | Jetpack-owned repository and table behavior |

The existing compiled `packages/` artifacts are reference material only. No `dist` output, generated
wrapper, or host-component template should be copied into Frosh Jetpack.

## Validation and diagnostics

Runtime registration validates:

- API major, IDs, entity names, route prefixes, and snippet roots;
- at least one listing column and detail card/field where those pages are enabled;
- unique IDs and valid `before`/`after` references;
- field-specific requirements such as options or target entity;
- ACL role and extra-privilege structure;
- known built-in renderer types or an explicit custom component;
- duplicate CRUD, route, module, extension, column, card, field, and action IDs.

Errors use the prefix `[FroshJetpack Admin CRUD]`, include the registration ID and precise property
path, and fail registration before a partial module is exposed.

Executable TypeScript callbacks cannot be safely evaluated by the PHP
`frosh:jetpack:validate` command. API version 1 therefore uses TypeScript plus runtime validation for
Administration CRUD definitions. If CLI validation of the complete definition becomes mandatory, the
serializable module/listing/detail metadata must move to a YAML manifest and hooks must remain a
separate JavaScript extension; PHP must not execute built Administration JavaScript.

## Current boundaries

The registration, module shell, listing, detail/create, translation, ACL, action, bulk-operation, and
cross-plugin extension paths are implemented. The following developer-experience features are not
part of the current contract:

- a published standalone TypeScript typings package;
- CRUD-specific Storybook scenarios and a demo consumer;
- dirty-state navigation guards, inline editing, and advanced media fields.

None of these follow-ups is required to use API version 1. Use a custom registered component when the
built-in field or cell renderers do not cover an extension-specific interface.
