# Scoped extension configuration

Jetpack configuration gives Symfony bundles a typed YAML definition, tabs and sections in the
Administration, sales-channel and language scopes, and extension-owned persistence outside Shopware's
`system_config` table.

## Define configuration

Add `Resources/config/jetpack.yaml` to any active Symfony bundle:

```yaml
# yaml-language-server: $schema=https://raw.githubusercontent.com/FriendsOfShopware/jetpack/main/src/Resources/schema/configuration-1.json
version: 1
label:
    en-GB: My plugin
    de-DE: Mein Plugin

tabs:
    general:
        label:
            en-GB: General
            de-DE: Allgemein
        position: 10
        sections:
            behavior:
                label:
                    en-GB: Behavior
                    de-DE: Verhalten
                fields:
                    enabled:
                        type: bool
                        scope: sales-channel
                        default: true
                        label:
                            en-GB: Enabled
                            de-DE: Aktiv

                    headline:
                        type: text
                        scope: sales-channel-language
                        default: Welcome
                        minLength: 3
                        maxLength: 80
                        label:
                            en-GB: Headline
                            de-DE: Überschrift

                    deliveryMode:
                        type: single-select
                        default: standard
                        label:
                            en-GB: Delivery mode
                            de-DE: Liefermodus
                        options:
                            standard:
                                label:
                                    en-GB: Standard
                                    de-DE: Standard
                            express:
                                label:
                                    en-GB: Express
                                    de-DE: Express

                    features:
                        type: multi-select
                        default: [search]
                        label:
                            en-GB: Features
                            de-DE: Funktionen
                        options:
                            search:
                                label:
                                    en-GB: Search
                                    de-DE: Suche
                            export:
                                label:
                                    en-GB: Export
                                    de-DE: Export
```

Projects can also map the bundled `src/Resources/schema/configuration-1.json` file in their IDE.
Validate this definition together with every other Jetpack declaration in the plugin:

```bash
bin/console frosh:jetpack:validate MyPlugin
```

Omit the bundle name to validate all active Shopware bundles. See the [validation guide](../validation/README.md)
for the complete set of checks.

Supported field types are `bool`, `int`, `float`, `text`, `textarea`, `password`, `single-select`, and
`multi-select`. A password field masks the Administration input; its value is not encrypted at rest.

## Read scalar values

The API only needs the regular Shopware `Context`. A sales-channel ID is optional, so callers do not
have to construct a `SalesChannelContext`.

Inject `ConfigurationService` and use a typed scalar getter:

```php
use Frosh\Jetpack\Configuration\ConfigurationService;
use Shopware\Core\Framework\Context;

final class MyService
{
    public function __construct(private readonly ConfigurationService $configuration)
    {
    }

    public function isEnabled(Context $context, ?string $salesChannelId = null): bool
    {
        return $this->configuration->getBool(
            MyPlugin::class,
            'enabled',
            $context,
            $salesChannelId,
        );
    }
}
```

## Load a configuration DTO

For several values, define an immutable configuration DTO. Constructor parameter names map to YAML
keys; `#[ConfigKey]` handles a different property name. Backed enums are hydrated from matching
configuration values.

```php
use Frosh\Jetpack\Attribute\ConfigKey;
use Frosh\Jetpack\Attribute\JetpackConfig;

enum DeliveryMode: string
{
    case Standard = 'standard';
    case Express = 'express';
}

#[JetpackConfig(MyPlugin::class)]
final readonly class MyPluginConfig
{
    /** @param list<string> $features */
    public function __construct(
        public bool $enabled,
        #[ConfigKey('headline')]
        public string $title,
        public DeliveryMode $deliveryMode,
        public array $features,
    ) {
    }
}
```

Load it where it is needed:

```php
$config = $configuration->load(
    MyPluginConfig::class,
    $context,
    $salesChannelId,
);
```

The generic return annotation means PHPStan understands the concrete DTO when the class name is
statically known.

## Scopes and inheritance

Each field declares one scope:

| Scope                    | Sales channel | Language |
| ------------------------ | ------------: | -------: |
| `global`                 |            No |       No |
| `sales-channel`          |           Yes |       No |
| `language`               |            No |      Yes |
| `sales-channel-language` |           Yes |      Yes |

Language inheritance comes from `Context::getLanguageIdChain()`. For a
`sales-channel-language` field, lookup order is deterministic:

1. selected sales channel and current language;
2. selected sales channel and each parent language;
3. selected sales channel, language-independent;
4. global sales channel and current language;
5. global sales channel and each parent language;
6. global and language-independent;
7. YAML default.

Scopes that do not use a dimension skip the corresponding levels. Reads of all keys in one bundle
share a request-local database result, which is reset after writes and at the end of the request.

## Administration and persistence

Jetpack registers a settings module that discovers active bundles with `jetpack.yaml`, renders their
tabs and sections, and lets administrators choose a sales channel and language. Overrides can be
removed to reveal the inherited value and its source.

Values use a dedicated table and a unique address made from bundle name, key, optional sales-channel
ID, and optional language ID. Foreign keys remove scoped rows when a sales channel or language is
deleted. Uninstalling Frosh Jetpack removes the table unless Shopware's “keep user data” option is
selected.
