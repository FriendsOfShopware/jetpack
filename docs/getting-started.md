# Getting started

This guide gets Frosh Jetpack running and proves the integration with a small scheduled task. The same
service discovery and validation setup is shared by the other features.

!!! info "Development documentation"

    These pages describe the current `main` branch and its unreleased PHP feature set. Until that
    implementation is tagged and `frosh/jetpack` is published on Packagist, use a source checkout
    through the static-plugin or Composer path repository already configured by your Shopware project.

## Requirements

| Dependency | Supported versions |
| --- | --- |
| Shopware | `>=6.6 <6.8` |
| PHP | `>=8.2 <8.6` |
| Database | MySQL 8+ or MariaDB 10.11+ |
| Doctrine DBAL | `^3.9` or `^4.0` |

See [compatibility and release status](compatibility.md) before adopting Jetpack in a published
extension.

## Make Jetpack available

For this Shopware source tree, Frosh Jetpack lives at
`custom/static-plugins/FroshJetpack`. In another development project, expose the checkout through the
equivalent static-plugin or Composer path-repository setup.

When the PHP package is published, the regular installation command will be:

```bash
composer require frosh/jetpack
```

Refresh the Shopware plugin list and activate Jetpack:

```bash
bin/console plugin:refresh
bin/console plugin:install --activate FroshJetpack
```

A distributable consumer extension should also declare `frosh/jetpack` in its Composer `require`
section. That dependency is the runtime compatibility boundary; do not copy Jetpack classes or
compiled Administration assets into the consumer.

## Register your extension services

Jetpack discovers entity definitions, scheduled tasks, and generated Symfony commands through the
normal service container. A typical `Resources/config/services.yaml` for a plugin whose PHP source
lives below `src/` is:

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    Acme\ReviewPlugin\:
        resource: "../../"
        exclude:
            - "../../Resources/"
```

Keep your existing service configuration if it already autowires and autoconfigures the classes you
want Jetpack to discover.

## Create a one-class scheduled task

Ask Jetpack to scaffold a task for an active bundle:

```bash
bin/console frosh:jetpack:make:scheduled-task \
    AcmeReviewPlugin \
    CleanupExpiredReviews \
    --interval=300 \
    --dry-run
```

The dry run shows the complete target and generated source without writing anything. Run it again
without `--dry-run` when the plan looks right.

The generated service has one public contract:

```php
use Frosh\Jetpack\Attribute\AsScheduledTask;
use Shopware\Core\Framework\Context;

#[AsScheduledTask(
    interval: 300,
    name: 'acme_review_plugin.cleanup_expired_reviews',
)]
final readonly class CleanupExpiredReviews
{
    public function __construct(private ReviewCleaner $cleaner)
    {
    }

    public function __invoke(Context $context): void
    {
        $this->cleaner->cleanup($context);
    }
}
```

Constructor injection works normally. Jetpack creates a fresh CLI `Context` for each execution and
adapts the service to Shopware's message/handler model internally.

## Validate and run it

Validate all Jetpack declarations owned by the bundle:

```bash
bin/console frosh:jetpack:validate AcmeReviewPlugin
```

Then use Shopware's existing task operations:

```bash
bin/console scheduled-task:register
bin/console scheduled-task:list
bin/console scheduled-task:run-single acme_review_plugin.cleanup_expired_reviews
```

The task remains visible to Shopware operations and uses the normal queue, statuses, retries, and
merchant-configured interval.

## Pick the next feature

- Need merchant settings? Define [typed, scoped configuration](configuration/README.md).
- Need persistence? Scaffold an [attributed DAL entity](entities/README.md), then generate its
  reversible migration from a committed snapshot.
- Need lifecycle-managed resources? Add [custom fields](custom-fields/README.md) or
  [mail templates](mail-templates/README.md) as YAML plus version-controlled content.
- Need an Administration screen? Choose the stable [component catalog](administration-components/README.md)
  or a complete [declarative CRUD module](administration-crud/README.md).
- Building Shopping Experiences content? Register a
  [declarative CMS element](administration-cms-elements/README.md).
- Want to see the pieces together? Build the [Recipe feature](tutorials/recipe.md) from entity to
  Storefront output.
- Adding more declarations? Keep `frosh:jetpack:validate <bundle>` in CI and add
  `frosh:jetpack:entity:check <bundle>` when the plugin owns Jetpack entities.
