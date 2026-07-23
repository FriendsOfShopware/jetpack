# Migrating Administration controls

Use the mapping below when moving an extension from version-dependent Shopware/Meteor controls to
Frosh Jetpack.

| Existing control                      | Jetpack replacement                           | Important API change                                                              |
| ------------------------------------- | --------------------------------------------- | --------------------------------------------------------------------------------- |
| `sw-button` / `mt-button`             | `jetpack-button`                              | `is-loading` becomes `loading`; native attributes replace position-specific props |
| `sw-text-field` / `mt-text-field`     | `jetpack-text-field`                          | `v-model:value` becomes `v-model`                                                 |
| `sw-number-field` / `mt-number-field` | `jetpack-number-field`                        | `v-model:value` becomes `v-model`; empty value is `null`                          |
| `sw-password-field`                   | `jetpack-password-field`                      | `v-model:value` becomes `v-model`                                                 |
| `sw-textarea-field` / `mt-textarea`   | `jetpack-textarea`                            | `v-model:value` becomes `v-model`                                                 |
| `sw-switch-field` / `mt-switch`       | `jetpack-switch`                              | Boolean `v-model`                                                                 |
| `sw-checkbox-field` / `mt-checkbox`   | `jetpack-checkbox`                            | Boolean `v-model`                                                                 |
| `sw-single-select` / `mt-select`      | `jetpack-select` or `jetpack-search-select`   | Options use `{ value, label }`; scalar `v-model`                                  |
| `sw-multi-select`                     | `jetpack-multi-select`                        | Array `v-model`                                                                   |
| `sw-entity-single-select`             | `jetpack-entity-select`                       | `entity`, optional `criteria`, scalar `v-model`                                   |
| `sw-entity-multi-id-select`           | `jetpack-entity-multi-select`                 | `v-model` is a Shopware entity collection, not an array of IDs                    |
| `sw-card` / `mt-card`                 | `jetpack-card`                                | Native `data-*` attributes replace host-only identifiers                          |
| `sw-container`                        | `jetpack-grid` / `jetpack-stack`              | Fixed responsive layout props replace CSS column strings                          |
| `sw-tabs` + `sw-tabs-item`            | `jetpack-tabs`                                | One `tabs` array and scalar `v-model`                                             |
| `sw-loader`                           | `jetpack-loader`                              | Accessible `label` prop                                                           |
| `sw-empty-state`                      | `jetpack-empty-state`                         | Jetpack icon names or an `icon` slot                                              |
| `sw-modal`                            | `jetpack-modal`                               | Boolean `v-model`; `close` event                                                  |
| `sw-data-grid`                        | `jetpack-data-table` / `jetpack-entity-table` | Explicit columns, slots, and controlled selection/sorting                         |

Before:

```twig
<sw-text-field
    :value="endpoint"
    :label="$t('my-plugin.endpoint')"
    @update:value="endpoint = $event"
/>
```

After:

```twig
<jetpack-url-field
    v-model="endpoint"
    :label="$t('my-plugin.endpoint')"
    autocomplete="url"
/>
```

Keep `sw-page` when the component is the Administration route shell. It is a host integration
boundary rather than an interchangeable visual control. Replace the page's smart-bar actions and
content controls with `jetpack-*` components.

Do not install or import `@shopware-ag/meteor-component-library` in the consumer extension for these
components. Frosh Jetpack intentionally owns the runtime and style surface.
