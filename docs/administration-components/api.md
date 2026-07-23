# Administration component API

All components forward unclaimed attributes to their root or primary native control. Props below are
in addition to standard Vue attributes and slots.

## Foundation

| Component        | Main props                                                                                  | Events and slots                            |
| ---------------- | ------------------------------------------------------------------------------------------- | ------------------------------------------- |
| `jetpack-button` | `variant`, `size`, `type`, `disabled`, `loading`, `block`, `square`, `href`, `to`, `target` | `click`; default, `icon-front`, `icon-back` |
| `jetpack-icon`   | `name`, `size`, `decorative`, `label`                                                       | —                                           |
| `jetpack-link`   | `href`, `to`, `target`, `disabled`                                                          | default                                     |
| `jetpack-loader` | `size`, `label`                                                                             | —                                           |

Button variants are `primary`, `secondary`, `tertiary`, and `critical`. Sizes are `small`, `default`,
and `large`. `to` renders a router link, `href` renders an anchor, and otherwise a native button is
used.

## Fields

Text, number, password, email, URL, textarea, select, search select, multi-select, entity select,
entity multi-select, datepicker, and color picker share `modelValue`, `id`, `label`, `helpText`,
`error`, `disabled`, and `required`. Text-like fields also accept `placeholder` and `readonly` where
meaningful.

| Component | Additional props | Value/event behavior |
| --- | --- | --- |
| `jetpack-number-field` | `min`, `max`, `step` | Emits `number \| null` |
| `jetpack-textarea` | `rows` | Emits `string` |
| `jetpack-select` | `options`, `placeholder` | Emits the selected option's typed value |
| `jetpack-search-select` | `options`, `placeholder`, `searchPlaceholder`, `emptyText` | Emits a scalar and `search` |
| `jetpack-multi-select` | same as search select | Emits an array |
| `jetpack-entity-select` | `entity`, `criteria`, `labelProperty`, `emptyText`, `loadingText` | Loads DAL entities; emits `load-error` |
| `jetpack-entity-multi-select` | `entity`, `criteria`, `context`, `labelProperty`, `emptyText`, `loadingText`, `searchPlaceholder` | Updates a Shopware entity collection; emits `change` and `load-error` |
| `jetpack-datepicker` | `min`, `max` | Emits an ISO date string from the native date control |
| `jetpack-color-picker` | — | Emits a CSS hex color string |
| `jetpack-rich-text-editor` | common field props | Emits HTML through `update:modelValue` and `change` |

Options use this shape:

```ts
type JetpackOption = {
    value: string | number | boolean | null;
    label: string;
    disabled?: boolean;
    description?: string;
};
```

`jetpack-checkbox` and `jetpack-switch` accept `modelValue`, `label`, `helpText`, `disabled`, and
`required`; they emit `update:modelValue` and `change` with a boolean.

`jetpack-inheritance-control` accepts `inherited`, `label`, `inheritLabel`, `overwriteLabel`, and
`disabled`. Use `v-model:inherited`; its default slot receives `{ inherited }`.

`jetpack-entity-multi-select` requires a mutable Shopware entity collection as `modelValue`, not an
array of IDs. It preserves loaded entity instances, searches the first 25 matching records by default,
and emits the synchronized collection through `update:modelValue` and `change`.

## Layout, feedback, and navigation

| Component             | Main props                                     | Events and slots                                            |
| --------------------- | ---------------------------------------------- | ----------------------------------------------------------- |
| `jetpack-card`        | `title`, `subtitle`, `compact`                 | default, `header`, `actions`, `footer`                      |
| `jetpack-grid`        | `columns` (1–4), `gap`, `align`, `tag`         | default                                                     |
| `jetpack-stack`       | `direction`, `gap`, `align`, `tag`             | default                                                     |
| `jetpack-empty-state` | `title`, `description`, `icon`                 | default actions, `icon`                                     |
| `jetpack-banner`      | `variant`, `title`, `message`, `dismissible`   | `dismiss`; default, `actions`                               |
| `jetpack-badge`       | `variant`, `size`                              | default                                                     |
| `jetpack-progress`    | `value`, `label`, `showValue`, `indeterminate` | —                                                           |
| `jetpack-skeleton`    | `variant`, `size`                              | —                                                           |
| `jetpack-tabs`        | `modelValue`, `tabs`, `ariaLabel`              | `update:modelValue`, `change`; default receives `activeTab` |
| `jetpack-pagination`  | `modelValue`, `total`, `limit`, `ariaLabel`    | `update:modelValue`, `change`                               |

Tab items use `{ name, label, disabled?, error?, badge? }`.

## Overlays and menus

| Component                                      | Main props                                                        | Events and slots                                                                     |
| ---------------------------------------------- | ----------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| `jetpack-modal`                                | `modelValue`, `title`, `size`, `closeOnBackdrop`, `closeOnEscape` | `update:modelValue`, `close`; default, `header`, `footer`                            |
| `jetpack-tooltip`                              | `text`, `position`                                                | default trigger                                                                      |
| `jetpack-popover`                              | `modelValue`, `position`, `closeOnOutside`                        | `update:modelValue`, `open`, `close`; `trigger` receives `{ open, toggle }`, default |
| `jetpack-action-menu` / `jetpack-context-menu` | `items`, `ariaLabel`, `position`                                  | `select`; `trigger` receives `{ open, toggle }`                                      |

Menu items use `{ id, label, disabled?, critical?, icon? }`.

## Tables and charts

`jetpack-data-table` accepts `items`, `columns`, `itemKey`, `selectable`, `modelValue`, `loading`,
`emptyText`, `sortBy`, and `sortDirection`. It emits `update:modelValue`, `selection-change`,
`sort-change`, and `row-click`. A cell can be customized with `#column-<property>="{ item, value,
column }"`; row actions use `#actions="{ item }"`.

Columns use `{ property, label, sortable?, align?, width? }`. Dot-separated properties read nested
values.

`jetpack-entity-table` adds `entity`, `criteria`, and `limit`, loads with the DAL repository factory,
and emits `load` or `load-error`. Paging and sorting cause a new repository search.

`jetpack-chart` accepts `points`, `type` (`bar` or `line`), `title`, and `ariaLabel`. Points use
`{ label, value }`. The chart is rendered as accessible inline SVG without a chart dependency.

## Media

| Component               | Main props                                                       | Events and slots              |
| ----------------------- | ---------------------------------------------------------------- | ----------------------------- |
| `jetpack-avatar`        | `src`, `name`, `label`, `size`                                   | —                             |
| `jetpack-media-preview` | `src`, `mediaType`, `fileName`, `alt`, `caption`, `removable`    | `remove`; `caption`           |
| `jetpack-media-upload`  | `accept`, `multiple`, common field props, `title`, `description` | `update:modelValue`, `change` |

The upload value is a `File` for single selection and `File[]` for multiple selection. Uploading to
Shopware's media API remains an application decision; the UI component owns selection and drag/drop,
not persistence.
