# Development scaffolding

Frosh Jetpack provides safe maker commands for repetitive extension-development files. The makers
generate Jetpack-native code for Shopware 6.6 and 6.7 without depending on Symfony MakerBundle or
Shopware's internal plugin scaffolding classes.

Discover the complete maker namespace with:

```bash
bin/console list frosh:jetpack:make
```

## Available makers

### Entity

```bash
bin/console frosh:jetpack:make:entity AcmeReview Review
bin/console frosh:jetpack:make:entity AcmeReview Review --table=acme_review_entry
```

This creates the attributed entity, concrete definition, and typed collection below the bundle's
`Entity/Review/` directory. The existing `frosh:jetpack:entity:make` spelling remains an alias.

### Reversible migration

```bash
bin/console frosh:jetpack:make:migration AcmeReview AddReviewStatus
```

The filename and class use the exact current Unix timestamp, for example
`Migration1785000123AddReviewStatus.php`. The generated class extends Jetpack's `Migration` and contains
both `up()` and `down()` methods. The maker does not inspect or modify the database and does not execute
the migration.

### Scheduled task

```bash
bin/console frosh:jetpack:make:scheduled-task AcmeReview CleanupExpiredReviews --interval=300
```

This creates one attributed service class. By default, the maker writes an explicit stable task name
from the bundle prefix and class name. Override it and the failure behavior when required:

```bash
bin/console frosh:jetpack:make:scheduled-task AcmeReview CleanupExpiredReviews \
    --interval=300 \
    --name=acme_review.cleanup_expired_reviews \
    --reschedule-on-failure
```

The bundle must load the generated class as an autowired and autoconfigured service, matching the
requirements of the [scheduled-task feature](../scheduled-tasks/README.md).

### Symfony command

```bash
bin/console frosh:jetpack:make:command AcmeReview RebuildIndex
```

This creates `Command/RebuildIndexCommand.php` with `#[AsCommand]`. The default command name is derived
from the bundle and class, such as `acme-review:rebuild-index`. Both metadata values can be explicit:

```bash
bin/console frosh:jetpack:make:command AcmeReview RebuildIndex \
    --name=acme-review:rebuild \
    --description='Rebuilds the review index'
```

### Entity-backed CMS element

```bash
bin/console frosh:jetpack:make:cms-element \
    AcmeRecipePlugin \
    Recipe \
    --entity=acme_recipe \
    --label-property=title
```

This creates a standalone declarative Administration registration with its English snippet, a native
Shopware CMS resolver, and a Storefront Twig starting point:

```text
Resources/app/administration/src/cms-element/acme-recipe/index.js
Resources/app/administration/src/cms-element/acme-recipe/snippet/en-GB.json
Cms/RecipeCmsElementResolver.php
Resources/views/storefront/element/cms-element-acme-recipe.html.twig
```

The technical name defaults to the bundle's root namespace plus the element name, and the CMS config
field defaults to the element name in lower camel case. Override the inferred contract when needed:

```bash
bin/console frosh:jetpack:make:cms-element \
    AcmeRecipePlugin \
    Recipe \
    --entity=acme_recipe \
    --name=acme-recipe \
    --field=recipe \
    --definition='Acme\RecipePlugin\Entity\Recipe\RecipeDefinition' \
    --label-property=title
```

The maker deliberately does not edit the consumer's Administration entrypoint. Add
`import './cms-element/acme-recipe';` to its `main.js` or `main.ts`, ensure the generated resolver is
autoconfigured, then rebuild the Administration and compile the Storefront theme. The scaffold creates
an element that can replace a block slot; it does not create a draggable CMS block.

## Dry runs and file safety

Every maker supports `--dry-run`:

```bash
bin/console frosh:jetpack:make:migration AcmeReview AddReviewStatus --dry-run
```

The complete plan is validated and displayed without writing files. Before a normal write, Jetpack:

1. parses every generated PHP file;
2. checks every target before writing the first file;
3. treats an existing byte-identical file as unchanged;
4. rejects an existing file with different content instead of overwriting it;
5. uses atomic file writes and removes files created earlier in the same plan if a later write fails.

There is intentionally no force or overwrite option. Generated files belong to the consumer plugin as
soon as they are created, and later maker runs must not replace developer changes.

Makers target active Shopware bundles by bundle name or bundle class. They do not change Composer
metadata, service configuration, plugin lifecycle methods, caches, migrations, or database state.

## Generated-code workflow

Generated PHP is a starting point and contains explicit implementation markers. After editing it:

```bash
bin/console frosh:jetpack:validate AcmeReview
```

Entity declarations additionally use the entity diff/generation workflow. Scheduled tasks, Symfony
commands, and CMS resolvers require the normal autowired and autoconfigured service resource in the
consumer bundle.

CMS element names and field names are persisted in Shopping Experiences content. Treat them as stable
identifiers after editors start using the element. Generated files are consumer-owned starting points;
add other Administration locales and customize Storefront markup in the consuming extension.

The command names, arguments, options, generated Jetpack API usage, and safety semantics documented
here are the supported developer contract. Scaffold plans, renderers, generators, and writers are
internal implementation details.
