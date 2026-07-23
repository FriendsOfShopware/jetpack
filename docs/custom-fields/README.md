# YAML custom fields

Jetpack creates and updates Shopware custom field sets from a schema-validated YAML file. The feature
uses the long-standing custom-field DAL repositories and works on Shopware 6.6 and 6.7 without relying
on the newer core `CustomFieldSetPersister`, `custom-fields.xml`, or `extension_name` column.

## Define custom fields

Create `src/Resources/config/custom-fields.yaml` in a plugin:

```yaml
$schema: https://raw.githubusercontent.com/FriendsOfShopware/jetpack/main/src/Resources/schema/custom-fields-1.json
version: 1

sets:
    acme_product_details:
        label:
            en-GB: Product details
            de-DE: Produktdetails
        position: 10
        relations: [product]
        fields:
            acme_product_badge:
                type: single-select
                label:
                    en-GB: Badge
                    de-DE: Kennzeichnung
                helpText:
                    en-GB: Displayed near the product name
                options:
                    new:
                        label:
                            en-GB: New
                            de-DE: Neu
                    sale:
                        label:
                            en-GB: Sale
                            de-DE: Angebot

            acme_product_score:
                type: int
                label:
                    en-GB: Score
                min: 0
                max: 100
                step: 5
                includeInSearch: true
```

Set and field keys are their immutable Shopware technical names. Use a vendor/plugin prefix because
custom field names are globally unique. Labels, help text, placeholders, and option labels accept any
Shopware locale code; no default language is assumed.

## Supported declarations

A set supports `label`, `relations`, `fields`, `global`, `active`, and `position`. Relations accept normal
or extension-defined DAL entity names.

Fields share these options:

- `label`, `helpText`, `placeholder`, `position`, `active`, and `required`;
- `allowCustomerWrite` and `allowCartExpose`;
- `includeInSearch`, applied when the installed Shopware minor provides that DAL field.

Supported types are `int`, `float`, `text`, `text-area`, `bool`, `datetime`, `single-select`,
`multi-select`, `single-entity-select`, `multi-entity-select`, `color-picker`, `media-selection`, and
`price`. Numeric fields support `min`, `max`, and `step`. Select fields require `options`. Entity selects
require `entity` and optionally accept `labelProperty`.

Validate one plugin, or omit the bundle name to validate all active bundles that provide the file:

```bash
bin/console frosh:jetpack:validate AcmePlugin
```

The same command also validates the plugin's configuration, mail templates, entities, and reversible
migrations. See the [validation guide](../validation/README.md) for details.

`includeInSearch` is intentionally omitted from DAL writes on Shopware versions predating searchable
custom fields. The declaration remains valid, so updating Shopware and then updating the plugin enables
the option without changing the YAML.

## Lifecycle and ownership

No plugin base class, lifecycle override, XML file, or service registration is required. With Frosh
Jetpack active:

- sets are synchronized after plugin installation and update;
- removing a field, relation, or set from YAML removes that owned definition on the next update;
- destructive uninstall removes all sets owned by that plugin;
- uninstall with “keep user data” preserves the sets and Jetpack ownership records.

Definitions are authoritative. Storefront entity values live under the custom field technical name;
removing a field definition does not rewrite arbitrary entity JSON, but the field is no longer available
through the Administration definition. Rename a field by introducing a new key and migrating values
explicitly; Shopware treats field names and storage types as immutable.

Jetpack stores ownership in `frosh_jetpack_custom_field_set`, linked to Shopware's set IDs. It never
needs or writes the newer `custom_field_set.extension_name` column, so Shopware's XML lifecycle and
Jetpack's YAML lifecycle stay decoupled. Stable deterministic IDs allow Jetpack to adopt its definitions
again if its ownership table is recreated while the Shopware custom-field rows remain.
