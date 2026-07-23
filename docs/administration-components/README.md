# Administration components

Frosh Jetpack provides an independent `jetpack-*` component catalog for extensions that must run on
Shopware 6.6 and 6.7. The components do not render or import Meteor (`mt-*`) or legacy Shopware
(`sw-*`) controls. Their markup, behavior, semantic tokens, and public contracts live in Frosh
Jetpack, so a host component rename does not change an extension's API.

The components are registered automatically when Frosh Jetpack's Administration entry point loads.
Consumer extensions can use them directly in Twig templates; no npm dependency or component import is
required.

```twig
<jetpack-card title="API settings">
    <jetpack-stack>
        <jetpack-url-field
            v-model="endpoint"
            label="Endpoint"
            required
            autocomplete="url"
            data-testid="endpoint"
        />

        <jetpack-switch
            v-model="enabled"
            label="Enabled"
        />
    </jetpack-stack>

    <template #footer>
        <jetpack-button
            variant="primary"
            :loading="saving"
            @click="save"
        >Save</jetpack-button>
    </template>
</jetpack-card>
```

## Stable contract

- Two-way values use Vue 3's `modelValue` and `update:modelValue` contract. Use `v-model` without an
  argument.
- Named two-way state uses the matching Vue convention, for example `v-model:inherited`.
- Unclaimed attributes and listeners are forwarded to the meaningful native element. Standard
  `aria-*`, `data-*`, `autocomplete`, input constraints, and native listeners remain available.
- Controls emit normalized values: number fields emit `number | null`, check controls emit `boolean`,
  and multi-selects emit arrays.
- All components use semantic HTML and expose labels, errors, help text, keyboard controls, and ARIA
  relationships without a host UI component.
- Styling is isolated behind the fixed `--jetpack-*` semantic token namespace. It does not proxy
  Shopware or Meteor CSS variables.

Legacy aliases such as `value`, `update:value`, `isLoading`, or Meteor-specific appearance props are
intentionally not supported. These are new components and start with one clear Vue 3 API.

## Catalog

| Area                | Components                                                                                                                             |
| ------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| Foundation          | `jetpack-button`, `jetpack-icon`, `jetpack-link`, `jetpack-loader`                                                                     |
| Text input          | `jetpack-text-field`, `jetpack-number-field`, `jetpack-password-field`, `jetpack-email-field`, `jetpack-url-field`, `jetpack-textarea` |
| Choice input        | `jetpack-checkbox`, `jetpack-switch`, `jetpack-select`, `jetpack-search-select`, `jetpack-multi-select`, `jetpack-entity-select`, `jetpack-entity-multi-select` |
| Specialized input   | `jetpack-inheritance-control`, `jetpack-datepicker`, `jetpack-color-picker`, `jetpack-rich-text-editor`                                |
| Layout              | `jetpack-card`, `jetpack-grid`, `jetpack-stack`                                                                                        |
| Feedback            | `jetpack-empty-state`, `jetpack-banner`, `jetpack-badge`, `jetpack-progress`, `jetpack-skeleton`                                       |
| Navigation          | `jetpack-tabs`, `jetpack-pagination`                                                                                                   |
| Overlay and actions | `jetpack-modal`, `jetpack-tooltip`, `jetpack-popover`, `jetpack-action-menu`, `jetpack-context-menu`                                   |
| Data                | `jetpack-data-table`, `jetpack-entity-table`, `jetpack-chart`                                                                          |
| Media               | `jetpack-avatar`, `jetpack-media-preview`, `jetpack-media-upload`                                                                      |

The internal `jetpack-field-shell` supplies accessible label, help, and error relationships to all
field implementations. It is registered for composition but is not intended as the normal extension
entry point.

See the [API reference](api.md) for props, events, and slots, and the [migration guide](migration.md)
for replacing 6.6/6.7 host controls.

For an interactive visual catalog with editable controls, use the
[Storybook component viewer](storybook.md). It exercises the component groups against deterministic
fixtures without loading Shopware or Meteor UI components.

## Host integration boundary

`jetpack-entity-select`, `jetpack-entity-multi-select`, and `jetpack-entity-table` deliberately use
Shopware's stable DAL integration: the injected `repositoryFactory`, `Shopware.Data.Criteria`, and
`Shopware.Context.api`. Navigation uses Vue Router. A complete Administration page can continue to
use `sw-page` as the host shell while all content and actions use `jetpack-*` components.

No Meteor source code, templates, icons, or styles are copied. The visual language is implemented from
scratch with Frosh Jetpack's own markup and tokens.

## Rich text safety

`jetpack-rich-text-editor` emits HTML. Treat it like any other HTML input: validate and sanitize it at
the application's trust boundary before rendering user-controlled content. The editor deliberately
does not silently rewrite or sanitize a consumer's domain data.
