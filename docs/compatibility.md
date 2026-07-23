# Compatibility and release status

## Supported runtime

| Surface | Compatibility |
| --- | --- |
| Shopware | 6.6 and 6.7 (`>=6.6 <6.8`) |
| PHP | 8.2, 8.3, 8.4, and 8.5 |
| Symfony | The Symfony 6.4/7 route and service contracts shipped by supported Shopware versions |
| Doctrine DBAL | 3.9 and 4.x |
| Database | MySQL 8+ and MariaDB 10.11+ |
| Administration | Vue 3 on the supported 6.6 classic/Vite and 6.7 Vite hosts |

## Documentation version

The hosted site is built from `main`. The current source identifies the plugin as `0.3.0`, while the
new PHP feature set is still recorded under **Unreleased** in the changelog and `frosh/jetpack` is not
yet published on Packagist. Treat these pages as development documentation until that PHP
implementation is tagged and published.

Once the PHP package is available, a production extension should use an explicit Composer constraint
and test that constraint against the latest supported 6.6 and 6.7 minors.

## What stays stable for consumers

Jetpack's public surface is intentionally smaller than its implementation:

- PHP attributes, interfaces, services, DTO contracts, and commands documented in the feature guides;
- YAML formats described by the versioned JSON schemas;
- `jetpack-*` component props, events, slots, and semantic CSS token names;
- `FroshJetpack.Admin.Crud` API version 1 and its documented registration behavior;
- deterministic naming, fallback, lifecycle, and ownership semantics called out in each guide.

Generated proxy classes, cache paths, service IDs, internal registries, Vuex/Pinia adapters, schema
planner internals, and persistence helpers are not consumer APIs.

## Native Shopware boundaries

Jetpack reduces declarations without replacing the platform below them:

| Jetpack feature | Native runtime retained |
| --- | --- |
| Entities | Shopware DAL definitions, entities, collections, repositories, flags, and Criteria |
| Scheduled tasks | Shopware task registry, scheduler, Messenger worker, statuses, and lifecycle |
| Routing | Symfony attribute loader and Shopware route scopes |
| Administration components | Vue 3, Router, and stable Shopware DAL services where data access is needed |
| Administration CRUD | Shopware modules, routes, ACL, snippets, repositories, and `sw-page` shell |

This boundary is the main upgrade strategy: version-sensitive glue stays inside Jetpack, while
consumer code targets one documented contract.

## Known deliberate limits

- Entity migration generation compares PHP declarations with committed snapshots; it does not inspect
  a database or detect manual database drift.
- Destructive entity changes require `--allow-destructive`; rollback restores schema shape, not data
  already removed by a destructive change.
- Scheduled tasks use intervals, not cron expressions, and receive a CLI `Context`, not a
  `SalesChannelContext`.
- Route shortcuts add the route scope only. Paths, names, authentication, caching, and OpenAPI remain
  explicit consumer decisions.
- Administration rich-text input emits HTML and must be validated or sanitized at the application's
  trust boundary.
- Mail template locale matching is exact; regional fallback such as `de-CH` to `de-DE` is not
  synthesized.

Each feature guide documents its additional boundaries and lifecycle behavior.
