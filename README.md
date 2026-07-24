# Frosh Jetpack

Frosh Jetpack is a developer toolkit for Shopware 6.6 and 6.7. It replaces repetitive extension
plumbing with typed declarations and safe generators while keeping Shopware's native DAL, scheduler,
routing, plugin lifecycle, and Administration runtime underneath.

> The PHP package is currently developed on `main`; the new feature set is still listed as
> **Unreleased**. See the [compatibility and release status](docs/compatibility.md) before adopting it
> in a published extension.

## What is cool about it?

- **Declare:** typed, scoped configuration; concrete DAL entities; YAML custom fields and mail
  templates.
- **Automate:** one-class scheduled tasks and concise Storefront, Store API, and Admin API route
  attributes.
- **Build UI:** 42 stable Vue 3 Administration components, declarative DAL CRUD, and CMS element
  registration with shared canvas, settings, and picker components.
- **Ship safely:** dry-run scaffolding, database-independent entity snapshots, reversible migrations,
  merchant-edit preservation, and one aggregated validation command.

For the outcome-first tour, installation path, and complete guides, open the
[Frosh Jetpack documentation](docs/README.md).

## A one-class scheduled task

```php
use Frosh\Jetpack\Attribute\AsScheduledTask;
use Shopware\Core\Framework\Context;

#[AsScheduledTask(interval: 300, name: 'acme_review.cleanup_expired_reviews')]
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

Jetpack adapts this service to Shopware's task message and handler model. Shopware still owns the
queue, task registry, statuses, retries, lifecycle, and merchant interval overrides.

## Feature guides

| Area | Guides |
| --- | --- |
| Backend declarations | [Configuration](docs/configuration/README.md) · [Entities](docs/entities/README.md) · [Migrations](docs/migrations/README.md) |
| Lifecycle resources | [Custom fields](docs/custom-fields/README.md) · [Mail templates](docs/mail-templates/README.md) |
| Runtime shortcuts | [Scheduled tasks](docs/scheduled-tasks/README.md) · [Routing](docs/routing/README.md) |
| Administration | [Components](docs/administration-components/README.md) · [Declarative CRUD](docs/administration-crud/README.md) · [Declarative CMS elements](docs/administration-cms-elements/README.md) |
| Tutorials | [Build a Recipe feature](docs/tutorials/recipe.md) |
| Developer workflow | [Scaffolding](docs/scaffolding/README.md) · [Validation](docs/validation/README.md) · [Commands](docs/commands.md) |

## Documentation development

Preview the Zensical site with live reload:

```bash
uvx zensical@0.0.51 serve --open
```

Build exactly as CI does:

```bash
uvx zensical@0.0.51 build --clean --strict
```

The production site is written to `site/` and deployed to GitHub Pages from `main`.
The deployment workflow also builds the Administration Storybook and publishes it below
`/jetpack/storybook/`.

## Plugin development

```bash
composer test
../../../vendor/bin/phpstan analyse --configuration phpstan.neon.dist
```
