---
hide:
  - toc
---

<div class="jetpack-hero" markdown>
<span class="jetpack-kicker">Extension development, with the repetitive parts removed</span>

# Build Shopware extensions at product speed

Frosh Jetpack turns configuration, DAL entities, lifecycle resources, scheduled tasks, and
Administration screens into concise declarations—while the result still runs on Shopware's native
DAL, scheduler, routing, plugin lifecycle, and Vue 3 runtime.

<div class="jetpack-actions" markdown>
[Get started](getting-started.md){ .md-button .md-button--primary }
[Explore the features](#choose-your-shortcut){ .md-button }
</div>

<div class="jetpack-badges">
<span class="jetpack-badge">Shopware 6.6–6.7</span>
<span class="jetpack-badge">PHP 8.2–8.5</span>
<span class="jetpack-badge">42 public Admin components</span>
<span class="jetpack-badge">MIT licensed</span>
</div>
</div>

## One toolkit, four useful outcomes

<div class="jetpack-grid">
<div class="jetpack-card" markdown>

### :material-code-braces: Declare

Describe typed configuration, concrete DAL entities, custom fields, and mail templates close to the
extension that owns them. JSON schemas catch mistakes before Shopware boots.

</div>
<div class="jetpack-card" markdown>

### :material-cog-sync: Automate

Replace a scheduled-task message/handler pair with one attributed service. Use scoped route
attributes without hiding paths, names, authentication, or other important behavior.

</div>
<div class="jetpack-card" markdown>

### :material-view-dashboard-edit: Build Administration UI

Use a stable Vue 3 component catalog across Shopware 6.6 and 6.7, register a complete DAL CRUD
module, or replace a CMS element's repeated Admin files with one field declaration.

</div>
<div class="jetpack-card" markdown>

### :material-shield-check: Ship safely

Preview scaffolds, generate reversible schema changes from committed snapshots, preserve merchant
mail edits, and validate every declaration through one CI-friendly command.

</div>
</div>

## See the difference

A normal Shopware scheduled task needs a message class and a handler class. Jetpack keeps Shopware's
queue, task registry, status handling, and merchant interval overrides, but your extension owns just
the work:

```php
use Frosh\Jetpack\Attribute\AsScheduledTask;
use Shopware\Core\Framework\Context;

#[AsScheduledTask(
    interval: 300,
    name: 'acme_review.cleanup_expired_reviews',
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

That is the recurring Jetpack idea: make the declaration small, keep the underlying Shopware
contract recognizable.

## Choose your shortcut

| I want to… | Start with | What Jetpack removes |
| --- | --- | --- |
| give merchants scoped, inherited settings | [Configuration](configuration/README.md) | custom settings modules, persistence, and manual type conversion |
| add typed DAL storage | [Entities](entities/README.md) | repetitive definition wiring and database-dependent schema diffs |
| own reversible plugin changes | [Migrations](migrations/README.md) | lifecycle overrides, history bookkeeping, and locking |
| ship custom fields from YAML | [Custom fields](custom-fields/README.md) | brittle install/update synchronization |
| keep mail templates in Git | [Mail templates](mail-templates/README.md) | locale synchronization and unsafe overwrites of merchant edits |
| run background work | [Scheduled tasks](scheduled-tasks/README.md) | the message/handler pairing and registration boilerplate |
| declare the right route scope | [Routing](routing/README.md) | repetitive `_routeScope` defaults and imports |
| use stable Administration controls | [Components](administration-components/README.md) | direct coupling to changing `sw-*` and `mt-*` controls |
| create a DAL management module | [Administration CRUD](administration-crud/README.md) | repeated listing, form, ACL, action, and translation plumbing |
| build a CMS element | [Administration CMS elements](administration-cms-elements/README.md) | separate Admin registration, canvas, config, and preview components |
| see the whole workflow | [Recipe tutorial](tutorials/recipe.md) | guessing how entities, migrations, CRUD, and CMS fit together |
| generate a safe starting point | [Scaffolding](scaffolding/README.md) | copy/paste without dry runs or conflict protection |
| fail once with all declaration errors | [Validation](validation/README.md) | feature-by-feature validation loops |

## Native underneath, extension-owned on top

Jetpack is deliberately not another ORM, task scheduler, or plugin base class:

- entities compile to normal Shopware DAL definitions and repositories;
- scheduled tasks use Shopware's registry, Messenger worker, lifecycle, and status table;
- routes are native Symfony route attributes with one Shopware scope default;
- migrations, custom fields, mail templates, and configuration retain explicit extension ownership;
- the Administration compatibility layer absorbs 6.6/6.7 UI differences without exposing Vuex,
  Pinia, or host form controls as your API.

Start with [installation and your first one-class task](getting-started.md), or use the
[command reference](commands.md) when you already know what you want to build.
