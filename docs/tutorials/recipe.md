# Build a Recipe feature end to end

This tutorial builds a small `AcmeRecipePlugin` feature through the complete Frosh Jetpack workflow:

```text
attributed PHP entity
    → committed schema snapshot and reversible migration
    → declarative Administration CRUD
    → declarative CMS element
    → native Shopware storefront resolver and Twig
```

The finished feature lets an administrator create recipes, select one in a Shopping Experiences slot,
and render its title, preparation time, and description in the Storefront.

## Before you start

Install and activate Frosh Jetpack and your `AcmeRecipePlugin`. Jetpack's maker commands resolve active
bundles, so the consumer plugin must be active before scaffolding:

```bash
bin/console plugin:refresh
bin/console plugin:install --activate FroshJetpack
bin/console plugin:install --activate AcmeRecipePlugin
```

This guide assumes the plugin class is `Acme\RecipePlugin\AcmeRecipePlugin` and its implementation
lives below `custom/plugins/AcmeRecipePlugin/src/`.

## 1. Scaffold the DAL entity

Preview the files first:

```bash
bin/console frosh:jetpack:make:entity \
    AcmeRecipePlugin \
    Recipe \
    --table=acme_recipe \
    --dry-run
```

Run the same command without `--dry-run` when the plan looks correct. Jetpack creates:

```text
src/Entity/Recipe/RecipeEntity.php
src/Entity/Recipe/RecipeCollection.php
src/Entity/Recipe/RecipeDefinition.php
```

Define the business fields in `RecipeEntity.php`:

```php
<?php declare(strict_types=1);

namespace Acme\RecipePlugin\Entity\Recipe;

use Frosh\Jetpack\Attribute\ApiScope;
use Frosh\Jetpack\Attribute\Field;
use Frosh\Jetpack\Attribute\FieldType;
use Frosh\Jetpack\Attribute\Id;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

final class RecipeEntity extends Entity
{
    #[Id]
    public string $id;

    #[Field(api: ApiScope::All, length: 255)]
    public string $title;

    #[Field(type: FieldType::Text, api: ApiScope::All)]
    public string $description;

    #[Field(api: ApiScope::All)]
    public int $preparationTime;
}
```

Non-null PHP properties infer Shopware's DAL `Required` flag, so the declaration does not repeat
`required: true`. `ApiScope::All` exposes the fields to the Admin and Store APIs.

The generated collection remains a normal typed Shopware collection:

```php
<?php declare(strict_types=1);

namespace Acme\RecipePlugin\Entity\Recipe;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<RecipeEntity> */
final class RecipeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return RecipeEntity::class;
    }
}
```

The generated definition connects that entity and collection to the DAL:

```php
<?php declare(strict_types=1);

namespace Acme\RecipePlugin\Entity\Recipe;

use Frosh\Jetpack\Attribute\Entity as EntityDeclaration;
use Frosh\Jetpack\Entity\JetpackEntityDefinition;

#[EntityDeclaration(
    name: 'acme_recipe',
    entity: RecipeEntity::class,
    collection: RecipeCollection::class,
)]
final class RecipeDefinition extends JetpackEntityDefinition
{
}
```

Make sure the plugin autowires and autoconfigures these classes in
`src/Resources/config/services.yaml`:

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    Acme\RecipePlugin\:
        resource: "../../"
        exclude:
            - "../../Resources/"
            - "../../AcmeRecipePlugin.php"
```

Shopware adds the DAL definition tag through autoconfiguration; Jetpack adds its own definition tag.
No repository service is required.

## 2. Generate and apply the migration

Validate the declaration and inspect the database-independent diff:

```bash
bin/console frosh:jetpack:validate AcmeRecipePlugin
bin/console frosh:jetpack:entity:diff AcmeRecipePlugin
```

Generate the snapshot and reversible migration:

```bash
bin/console frosh:jetpack:entity:generate \
    AcmeRecipePlugin \
    --name=create_recipe
```

Review and commit all generated artifacts together:

```text
src/Migration/Migration<TIMESTAMP>CreateRecipe.php
src/Resources/jetpack/entity-schema/<TIMESTAMP>_create_recipe.json
src/Resources/jetpack/entity-schema/_journal.json
```

Do not run `make:migration` for the same schema change: `entity:generate` already creates the guarded
`up()` and reverse-order `down()` operations. Apply the pending migration to this active development
installation:

```bash
bin/console frosh:jetpack:migrate AcmeRecipePlugin
```

Normal plugin install and update flows apply it automatically on other installations. Keep this drift
check in CI:

```bash
bin/console frosh:jetpack:entity:check AcmeRecipePlugin
```

## 3. Register the Administration CRUD

Create `src/Resources/app/administration/src/main.js`. One call supplies the module, routes, ACL,
listing, form, repository behavior, and DAL field renderers:

```js
import enGB from './snippet/en-GB.json';

Shopware.Locale.extend('en-GB', enGB);

FroshJetpack.Admin.Crud.register({
    apiVersion: 1,
    id: 'acme.recipe',
    entity: 'acme_recipe',
    snippets: 'acme-recipe',

    module: {
        titleSnippet: 'acme-recipe.general.title',
        descriptionSnippet: 'acme-recipe.general.description',
        color: '#0870ff',
        navigation: {
            parent: 'sw-content',
            position: 100,
        },
        settings: false,
    },

    acl: {
        key: 'acme_recipe',
        category: 'permissions',
        parent: 'content',
    },

    listing: {
        search: false,
        defaultSort: [{ property: 'title', direction: 'ASC' }],
        columns: [
            {
                id: 'title',
                property: 'title',
                linkToDetail: true,
                sortable: true,
            },
            {
                id: 'preparationTime',
                property: 'preparationTime',
                type: 'number',
                sortable: true,
                align: 'end',
            },
        ],
    },

    detail: {
        labelProperty: 'title',
        cards: [
            {
                id: 'general',
                fields: [
                    {
                        id: 'title',
                        property: 'title',
                        type: 'text',
                        required: true,
                    },
                    {
                        id: 'description',
                        property: 'description',
                        type: 'textarea',
                        required: true,
                    },
                    {
                        id: 'preparationTime',
                        property: 'preparationTime',
                        type: 'number',
                        required: true,
                        min: 1,
                        step: 1,
                    },
                ],
            },
        ],
    },
});
```

Add `src/Resources/app/administration/src/snippet/en-GB.json`:

```json
{
  "acme-recipe": {
    "general": {
      "title": "Recipes",
      "description": "Create and manage recipes"
    },
    "list": {
      "columns": {
        "title": "Title",
        "preparationTime": "Preparation time"
      },
      "actions": {
        "create": "Add recipe",
        "edit": "Edit",
        "delete": "Delete"
      }
    },
    "detail": {
      "cards": {
        "general": {
          "title": "Recipe"
        }
      },
      "fields": {
        "title": "Title",
        "description": "Description",
        "preparationTime": "Preparation time in minutes"
      },
      "actions": {
        "save": "Save"
      }
    }
  },
  "sw-privileges": {
    "permissions": {
      "acme_recipe": {
        "label": "Recipes"
      }
    }
  }
}
```

## 4. Register the CMS element

Preview the complete cross-layer scaffold:

```bash
bin/console frosh:jetpack:make:cms-element \
    AcmeRecipePlugin \
    Recipe \
    --entity=acme_recipe \
    --label-property=title \
    --dry-run
```

Repeat without `--dry-run` to create:

```text
src/Resources/app/administration/src/cms-element/acme-recipe/index.js
src/Resources/app/administration/src/cms-element/acme-recipe/snippet/en-GB.json
src/Cms/RecipeCmsElementResolver.php
src/Resources/views/storefront/element/cms-element-acme-recipe.html.twig
```

The maker refuses to overwrite existing files and never edits the Administration entrypoint. Add its
one required import near the top of `main.js`:

```js
import './cms-element/acme-recipe';
```

The generated module registers the CMS definition and its own English snippets:

```js
FroshJetpack.Admin.Cms.register({
    apiVersion: 1,
    name: 'acme-recipe',
    labelSnippet: 'acme-recipe.cms.label',
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
    ],
});
```

That single object replaces the usual Administration element registration, canvas component,
configuration component, picker preview, and their templates. Jetpack uses the entity metadata to
load the selected recipe into `element.data.recipe` in the Administration canvas.

The names must match across all three runtime layers:

| Layer | Value |
| --- | --- |
| Administration registration | `acme-recipe` |
| resolver `getType()` | `acme-recipe` |
| Storefront template | `cms-element-acme-recipe.html.twig` |

## 5. Resolve the recipe in the Storefront

The Storefront still uses a normal Shopware CMS resolver so its context and data loading stay
explicit. Review the generated `src/Cms/RecipeCmsElementResolver.php`:

```php
<?php declare(strict_types=1);

namespace Acme\RecipePlugin\Cms;

use Acme\RecipePlugin\Entity\Recipe\RecipeDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayStruct;

final class RecipeCmsElementResolver extends AbstractCmsElementResolver
{
    private const CONFIG_KEY = 'recipe';

    public function getType(): string
    {
        return 'acme-recipe';
    }

    public function collect(
        CmsSlotEntity $slot,
        ResolverContext $resolverContext,
    ): ?CriteriaCollection {
        $config = $slot->getFieldConfig()->get(self::CONFIG_KEY);

        if ($config === null || !$config->isStatic() || $config->getValue() === null) {
            return null;
        }

        $collection = new CriteriaCollection();
        $collection->add(
            $this->resultKey($slot),
            RecipeDefinition::class,
            new Criteria([$config->getStringValue()]),
        );

        return $collection;
    }

    public function enrich(
        CmsSlotEntity $slot,
        ResolverContext $resolverContext,
        ElementDataCollection $result,
    ): void {
        $data = new ArrayStruct([self::CONFIG_KEY => null]);
        $slot->setData($data);

        $config = $slot->getFieldConfig()->get(self::CONFIG_KEY);
        if ($config === null || !$config->isStatic() || $config->getValue() === null) {
            return;
        }

        $entity = $result
            ->get($this->resultKey($slot))
            ?->getEntities()
            ->get($config->getStringValue());

        if ($entity !== null) {
            $data->set(self::CONFIG_KEY, $entity);
        }
    }

    private function resultKey(CmsSlotEntity $slot): string
    {
        return 'recipe_' . $slot->getUniqueIdentifier();
    }
}
```

Autoconfiguration supplies Shopware's `shopware.cms.data_resolver` tag. No manual resolver tag is
needed.

## 6. Render the Storefront element

Customize the generated starting point at
`src/Resources/views/storefront/element/cms-element-acme-recipe.html.twig`:

```twig
{% set recipe = element.data.get('recipe') %}

{% if recipe %}
    <article class="card cms-element-acme-recipe">
        <div class="card-body">
            <h2 class="card-title">{{ recipe.title }}</h2>

            <p class="text-body-secondary">
                {{ 'acme-recipe.cms.preparationTime'
                    |trans({ '%minutes%': recipe.preparationTime })
                    |sw_sanitize }}
            </p>

            <p class="card-text">
                {{ recipe.description|sw_sanitize|nl2br }}
            </p>
        </div>
    </article>
{% endif %}
```

Add the separate Storefront snippet at `src/Resources/snippet/storefront.en-GB.json`:

```json
{
  "acme-recipe": {
    "cms": {
      "preparationTime": "%minutes% min preparation time"
    }
  }
}
```

## 7. Build and try the complete flow

Validate the declarations and build the Administration:

```bash
bin/console frosh:jetpack:validate AcmeRecipePlugin
bin/console frosh:jetpack:entity:check AcmeRecipePlugin
composer build:js:admin
bin/console theme:compile
```

Then:

1. give the administrator role the generated `acme_recipe.viewer`, creator, editor, and deleter
   permissions;
2. open **Content → Recipes** and create a recipe;
3. open a Shopping Experiences layout and edit an existing block slot;
4. replace that slot's element with **Recipe**, choose the recipe, and save the layout;
5. open the assigned Storefront page and verify the rendered card.

The CMS declaration registers an element, not a draggable block. If editors need a dedicated Recipe
block in the sidebar, add a normal Shopware CMS block whose slot type is `acme-recipe`.

You now have one feature using Jetpack's entity compiler, database-independent migration generation,
lifecycle runner, Administration CRUD, stable field catalog, and declarative CMS runtime while the
Storefront continues to use Shopware's native resolver and Twig contracts.
