# Declarative CMS elements

Shopware CMS elements normally need separate Administration registrations, canvas, configuration,
preview, template, style, and snippet files. Frosh Jetpack keeps the Shopware CMS runtime, but replaces
the repeated Administration layer with one definition:

```js
FroshJetpack.Admin.Cms.register({
    apiVersion: 1,
    name: 'acme-recipe',
    labelSnippet: 'acme-recipe.cms.label',
    allowedPageTypes: ['landingpage', 'product_list'],
    fields: [
        {
            name: 'recipe',
            type: 'entity-select',
            entity: 'acme_recipe',
            labelProperty: 'title',
            labelSnippet: 'acme-recipe.cms.fields.recipe',
            placeholderSnippet: 'acme-recipe.cms.placeholders.recipe',
            required: true,
        },
        {
            name: 'showDescription',
            type: 'switch',
            labelSnippet: 'acme-recipe.cms.fields.showDescription',
            defaultValue: true,
        },
    ],
});
```

Jetpack registers one shared canvas component, settings form, and element-picker preview. The entity
field is projected to Shopware's native CMS config, including its DAL criteria, collector, enrichment,
and layout inheritance behavior. The selected `acme_recipe` entity is therefore available as
`element.data.recipe` in the Administration canvas.

## Public API

`FroshJetpack.Admin.Cms` is installed before normal Administration plugin modules:

```ts
interface CmsElementApi {
    register(definition: CmsElementDefinition): { name: string };
    get(name: string): Readonly<CmsElementDefinition> | undefined;
    all(): readonly Readonly<CmsElementDefinition>[];
}
```

Definitions are validated and stored as immutable snapshots. Registration names must contain at least
two lower-case kebab-case segments, such as `acme-recipe`; this keeps third-party names out of
Shopware's unnamespaced CMS registry. Field names use lower camel case and must be unique.

Consumer typings are not published as a separate npm package yet. JavaScript consumers can use the
global directly. TypeScript projects can keep a local ambient declaration for the API version they
consume, as described in the [CRUD guide](../administration-crud/README.md#global-api).

## Fields

All fields currently use Shopware's `static` CMS source. Jetpack renders them with its stable
`jetpack-*` controls and wraps each one in Shopware's CMS inheritance boundary.

| Type | Value | Additional options | Default |
| --- | --- | --- | --- |
| `text` | `string` | — | `""` |
| `textarea` | `string` | — | `""` |
| `number` | `number \| null` | `min`, `max`, `step` | `null` |
| `switch` | `boolean` | — | `false` |
| `select` | scalar or `null` | `options` | `null` |
| `entity-select` | entity ID or `null` | `entity`, `labelProperty`, `criteria` | `null` |

Every field requires `name`, `type`, and `labelSnippet`. The shared options are
`helpTextSnippet`, `placeholderSnippet`, `required`, `disabled`, and `defaultValue`.

Select options use snippet keys rather than literal labels:

```js
{
    name: 'layout',
    type: 'select',
    labelSnippet: 'acme-recipe.cms.fields.layout',
    defaultValue: 'card',
    options: [
        {
            value: 'card',
            labelSnippet: 'acme-recipe.cms.layouts.card',
        },
        {
            value: 'compact',
            labelSnippet: 'acme-recipe.cms.layouts.compact',
        },
    ],
}
```

Entity fields may customize the Criteria used for both the selector and CMS enrichment:

```js
{
    name: 'recipe',
    type: 'entity-select',
    entity: 'acme_recipe',
    labelProperty: 'title',
    labelSnippet: 'acme-recipe.cms.fields.recipe',
    criteria({ criteria }) {
        criteria.addAssociation('media');
    },
}
```

## Element options

`allowedPageTypes` is optional. Shopware's core page types are `page`, `landingpage`,
`product_list`, and `product_detail`; other extensions may add their own. Omitting the property allows
the element everywhere, while an empty array allows it nowhere.

`hidden: true` removes the element from the slot replacement picker without breaking existing slots.
`removable: false` prevents editors from replacing it.

## Administration versus storefront

This API removes the Administration boilerplate only. Storefront output remains explicit and native:

1. register an `AbstractCmsElementResolver` when the element needs DAL data;
2. render
   `Resources/views/storefront/element/cms-element-<name>.html.twig`;
3. compile the Administration and Storefront theme after changing their sources.

Keeping the resolver explicit makes sales-channel context, associations, availability rules, and data
shape visible in application code instead of hiding backend behavior in a generic abstraction.

The CMS element registration makes the element available when replacing an existing block slot. It
does not create a draggable CMS block. Register a normal Shopware block when editors should drag a
dedicated block directly from the sidebar.

See [Build a Recipe feature end to end](../tutorials/recipe.md) for the complete entity, generated
migration, Administration CRUD, declarative CMS element, storefront resolver, and Twig template.
