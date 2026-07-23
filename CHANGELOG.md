# Changelog

## Unreleased

- Add an extension-owned reversible migration contract with `up()` and `down()` methods.
- Automatically discover migrations through Shopware plugin lifecycle events, apply them on
  install/update, and roll them back newest-first after destructive uninstall.
- Keep consumer plugins on Shopware's regular `Plugin` base class; no Jetpack-specific plugin base
  class or lifecycle integration is required.
- Add bundle-scoped migration history, ordering validation, and MySQL/MariaDB locking independent of
  Shopware's migration table.
- Generate reversible Jetpack entity migrations from committed snapshots.
- Add JSON-schema-backed `custom-fields.yaml` definitions with automatic install, update, and
  destructive-uninstall synchronization on Shopware 6.6 and 6.7.
- Track Jetpack-owned custom field sets independently of Shopware's newer `extension_name` column.
- Replace feature-specific validators with one `frosh:jetpack:validate` command covering configuration,
  custom fields, mail templates, entities, and reversible migration declarations.
- Add JSON-schema-backed `mail-templates.yaml` definitions with conventional HTML/plain Twig bodies,
  automatic lifecycle and language synchronization, merchant-edit preservation, independent ownership,
  and stable template ID lookup.
- Add an independent Administration UI catalog with foundation, form, layout, feedback, navigation,
  overlay, table, rich-text, chart, media, and avatar components.
- Use Vue 3 `modelValue` contracts, native attribute forwarding, semantic HTML, and accessible states
  consistently across every `jetpack-*` component.
- Remove FroshJetpack configuration pages' dependency on changing `sw-*` and `mt-*` UI controls; only
  the stable `sw-page` host boundary remains.
- Add a standalone Vue 3 Storybook component viewer with interactive stories for the complete
  `jetpack-*` catalog, deterministic DAL fixtures, generated docs, and a static documentation build.
- Decouple the Storybook typecheck and Vite/esbuild build from Shopware's Administration
  `tsconfig.json`, allowing documentation CI to build from a standalone Frosh Jetpack checkout.
- Add a boot-order-safe global Administration CRUD API with typed DAL listings, detail/create forms,
  lifecycle hooks, association selectors, and immutable registration snapshots.
- Add deterministic cross-plugin CRUD extensions for columns, cards, fields, row actions, bulk
  actions, and detail actions, independent of plugin registration order.
- Add translated repository contexts, Shopware 6.6/6.7 DAL property-error adapters, bulk deletion,
  ACL-aware actions, and specialized field/cell renderers to the Administration CRUD runtime.
- Add `#[AsScheduledTask(interval: ...)]` for one-class, constructor-injected scheduled tasks with a
  regular Shopware `Context` argument.
- Generate deterministic internal Shopware task messages, reloadable runtime metadata, and handler
  adapters while keeping core persistence, lifecycle registration, queueing, status transitions, and
  merchant interval overrides.
- Reject invalid callable signatures, intervals, effective names, duplicate declarations, and
  collisions with native scheduled tasks during container compilation.
- Include attributed scheduled tasks in the bundle-scoped `frosh:jetpack:validate` report.
- Add `frosh:jetpack:migrate <plugin>` and the explicit `--all` mode for running pending Jetpack
  migrations without triggering plugin updates.
- Add native `StorefrontRoute`, `StoreApiRoute`, and `AdminApiRoute` Symfony attributes that apply the
  corresponding Shopware scope on classes or methods across Shopware 6.6 and 6.7.
- Add safe `frosh:jetpack:make:*` commands for entities, reversible migrations, attributed scheduled
  tasks, and Symfony console commands, with dry runs, idempotent reruns, conflict detection, PHP syntax
  validation, atomic writes, and rollback after partial write failures.

## 0.2.0

- Add extension-owned, attribute-driven concrete Shopware DAL definitions for Shopware 6.6 and 6.7.
- Add typed fields and enums, DAL flags, associations, translations, versioning, inheritance, indexes,
  foreign keys, and many-to-many mapping definitions.
- Add database-independent entity snapshot diffing and guarded Shopware migration generation.
- Add entity scaffold, validation, diff, generation, drift-check, and baseline commands.
