# YAML mail templates

Jetpack creates Shopware mail template types, templates, and translations from schema-validated YAML
metadata plus conventional Twig files. Consumer plugins keep using Shopware's regular `Plugin` base
class and do not need lifecycle methods or service registration.

## Declare a template

Create `src/Resources/config/mail-templates.yaml` in the plugin:

```yaml
$schema: https://raw.githubusercontent.com/FriendsOfShopware/jetpack/main/src/Resources/schema/mail-templates-1.json
version: 1
default-locale: en-GB

templates:
    acme_order_confirmation:
        available-entities:
            order: order
            salesChannel: sales_channel
            editOrderUrl: null
        translations:
            en-GB:
                name: Order confirmation
                subject: 'Your order {{ order.orderNumber }}'
                sender-name: '{{ salesChannel.name }}'
                description: Sent after order placement
            de-DE:
                name: Bestellbestätigung
                subject: 'Ihre Bestellung {{ order.orderNumber }}'
                sender-name: '{{ salesChannel.name }}'
                description: Wird nach der Bestellung versendet
```

The template key is the immutable, globally unique Shopware technical name. Prefix it with the vendor
or plugin name. Each `available-entities` key is a Twig variable. Its value is a DAL entity technical
name, or `null` for a scalar or value supplied outside the DAL. Jetpack validates every non-null entity
against Shopware's definition registry.

Store the actual bodies separately:

```text
src/Resources/mail-templates/
└── acme_order_confirmation/
    ├── de-DE.html.twig
    ├── de-DE.txt.twig
    ├── en-GB.html.twig
    └── en-GB.txt.twig
```

Both files are required for every declared translation. Jetpack rejects missing, empty, path-escaping,
or syntactically invalid Twig files. Keeping subjects and metadata in YAML while leaving larger bodies
in Twig makes templates easier to edit and lets IDE Twig support work normally.

## Language behavior

`default-locale` must exist on every declared template. Jetpack synchronizes a translation when its
locale exactly matches an installed Shopware language. The system language uses `default-locale` as a
fallback if its installed locale has no exact declaration. Adding a Shopware language triggers another
sync automatically, so a matching translation becomes available without updating the plugin.

This first version intentionally does not infer regional fallbacks such as `de-CH` from `de-DE`. Declare
each locale whose distinct installed language should receive content.

## Updates and merchant changes

The default `preserve-user-changes` policy records hashes of the last synchronized type name and mail
content. On plugin update, Jetpack updates unchanged content but preserves a translation after a merchant
has edited it in the Administration. Type names and mail content are tracked independently. Preserved
edits are logged.

Use the explicit authoritative policy only when the files must always win:

```yaml
templates:
    acme_order_confirmation:
        update-policy: overwrite
        available-entities:
            order: order
        translations:
            # ...
```

Removing a language or template from YAML removes the corresponding Jetpack-managed records on the next
plugin update. Renaming a technical name therefore means removing one template and creating another.

## Lifecycle and ownership

Jetpack listens to Shopware's post-install, post-update, and post-uninstall events:

- install and update synchronize the current declaration;
- destructive uninstall removes templates owned by that plugin;
- uninstall with “keep user data” preserves both Shopware records and Jetpack ownership;
- a template type is retained during cleanup if another Shopware mail template still references it.

Ownership and synchronization hashes live in Jetpack's own tables. Jetpack never adopts an existing
unowned mail template type with the same technical name; it reports a collision instead. Template and
type IDs are deterministic for the owning bundle and technical name.

## Resolve IDs in application code

Inject `MailTemplateRegistry` when a flow action, service, or fixture needs the stable Shopware IDs:

```php
use Acme\OrderMail\AcmeOrderMail;
use Frosh\Jetpack\MailTemplate\MailTemplateRegistry;

final class OrderMailHandler
{
    public function __construct(private readonly MailTemplateRegistry $mailTemplates)
    {
    }

    public function templateId(): string
    {
        return $this->mailTemplates
            ->get(AcmeOrderMail::class, 'acme_order_confirmation')
            ->templateId;
    }
}
```

The returned `MailTemplateReference` also exposes `templateTypeId`, `technicalName`, and `bundleName`.
Looking up a template that the bundle does not own throws a descriptive exception.

The declaration creates mail definitions only. It does not create Flow Builder rules, send messages,
or make a template sales-channel-specific; those remain normal Shopware application concerns.

## Validate

Validate one plugin, or omit the bundle name to validate all active bundles:

```bash
bin/console frosh:jetpack:validate AcmeOrderMail
```

Validation covers JSON Schema structure, default locales, DAL entity references, body-file conventions,
Twig syntax, and duplicate technical names across active Jetpack declarations without writing to the
database.
