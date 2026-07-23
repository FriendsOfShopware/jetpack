# Jetpack entities and code-first migrations

Jetpack entities are an extension-owned declaration layer for Shopware 6.6 and 6.7. The declaration
compiler produces regular Shopware `FieldCollection` objects; the runtime does not introduce another
repository, entity registry, persistence API, or generic shared definition service.

Migration generation is based only on two inputs: the current PHP declarations and the latest committed
Jetpack snapshot. A database connection is deliberately absent from the generator.

## Register definitions

Definitions must be normal autoconfigured Symfony services. For a bundle whose PHP classes live below
`src/`, a typical `src/Resources/config/services.yaml` contains:

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

Shopware's autoconfiguration adds the normal DAL definition tag. Frosh Jetpack adds its own discovery
tag to every class implementing `JetpackDefinition`. A concrete definition must not define constructor
dependencies.

## A minimal typed entity

```php
namespace Acme\ReviewPlugin\Entity\Review;

use Frosh\Jetpack\Attribute\ApiScope;
use Frosh\Jetpack\Attribute\Entity as EntityDeclaration;
use Frosh\Jetpack\Attribute\Field;
use Frosh\Jetpack\Attribute\Id;
use Frosh\Jetpack\Entity\JetpackEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

final class ReviewEntity extends Entity
{
    #[Id]
    public string $id;

    #[Field(api: ApiScope::All, length: 120)]
    public string $title;

    #[Field(api: ApiScope::Admin)]
    public ReviewState $state;

    /** @var array<string, mixed> */
    #[Field]
    public array $payload = [];
}

/** @extends EntityCollection<ReviewEntity> */
final class ReviewCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ReviewEntity::class;
    }
}

#[EntityDeclaration(
    name: 'acme_review',
    entity: ReviewEntity::class,
    collection: ReviewCollection::class,
)]
final class ReviewDefinition extends JetpackEntityDefinition
{
}
```

`string`, `int`, `float`, `bool`, `array`, `DateTimeInterface`/`DateTimeImmutable`, and string- or
integer-backed enums are inferred. Jetpack validates explicit built-in types against the PHP property
type so DAL hydration cannot fail later; `mixed` and a custom factory remain escape hatches.
`FieldType` can select an explicit storage/DAL type:

| `FieldType`             | DAL behavior                          | SQL storage                         |
| ----------------------- | ------------------------------------- | ----------------------------------- |
| `Uuid`                  | UUID converted by `IdField`/`FkField` | `BINARY(16)`                        |
| `String`                | bounded string                        | `VARCHAR(length)`                   |
| `Text`                  | long text                             | `LONGTEXT`                          |
| `Int`, `Float`, `Bool`  | scalar                                | `INT`, `DOUBLE`, `TINYINT(1)`       |
| `DateTime`, `Date`      | date value                            | `DATETIME(3)`, `DATE`               |
| `Json`                  | array/JSON                            | `JSON`                              |
| `Blob`                  | binary data                           | `LONGBLOB`                          |
| `Email`                 | validated email string                | `VARCHAR(length)`                   |
| `Price`, `CustomFields` | Shopware structured JSON              | `JSON`                              |
| `Enum`                  | Jetpack-owned backed enum field       | `VARCHAR` or `INT`                  |
| `Custom`                | extension-provided `FieldFactory`     | factory-provided deterministic type |

`Field` also exposes `required`, `api`, `writeProtected`, `runtime`, `computed`, `inherited`,
`inheritanceForeignKey`, `searchRanking`, `tokenize`, `allowHtml`, `allowEmptyString`, `translated`, and
database-default options. Runtime and translated fields are excluded from the owning table snapshot.

For specialized fields, implement the zero-constructor `FieldFactory` interface. `create()` returns the
normal DAL field and `sqlType()` returns its deterministic MySQL/MariaDB type; both receive the canonical
`FieldSchema`:

```php
final class SlugFieldFactory implements FieldFactory
{
    public function create(FieldSchema $schema): Field
    {
        return new StringField($schema->storageName, $schema->propertyName, 120);
    }

    public function sqlType(FieldSchema $schema): string
    {
        return 'VARCHAR(120)';
    }
}

#[Field(factory: SlugFieldFactory::class)]
public string $slug;
```

The factory class name is part of the committed snapshot. Generation still never asks a database to
infer the custom storage type. Persistent factories must return a `StorageAware` field whose property
and storage names match the supplied schema; Jetpack rejects a mismatched runtime/migration mapping.
Custom SQL types are limited to a type name, optional numeric precision/scale, and optional `UNSIGNED`
or `ZEROFILL` modifiers so a factory cannot append another DDL clause.
The resolved SQL type for custom and enum fields is committed in each snapshot, allowing a generated
`down()` to restore old schema after the original PHP factory or enum has been removed.

## Foreign keys and associations

The foreign-key property and association property are deliberately separate, matching Shopware DAL:

```php
#[ForeignKey(ProductDefinition::class, onDelete: OnDelete::Cascade)]
public string $productId;

#[ManyToOne(ProductDefinition::class, localField: 'productId')]
public ?ProductEntity $product = null;
```

Available association attributes are `ManyToOne`, `OneToOne`, `OneToMany`, and `ManyToMany`.
`OnDelete` supports `CASCADE`, `RESTRICT`, `SET NULL`, and `NO ACTION`; Jetpack rejects `SET NULL` on a
non-nullable property. Fields and associations with `ApiScope::None` have no `ApiAware` flag; use
`Admin`, `Store`, or `All` explicitly when they should be exposed. Use `Index` on the definition with
DAL property names, not database column names:

```php
#[Index(['productId', 'state'], unique: true)]
```

For a version-aware target, pair the foreign key with an explicit reference-version property. `forField`
is mandatory when more than one foreign key points to the same definition:

```php
#[ReferenceVersion(ProductDefinition::class, forField: 'productId')]
public string $productVersionId;
```

Jetpack generates a composite database foreign key for `(product_id, product_version_id)` and compiles
the properties to Shopware's normal `FkField` and `ReferenceVersionField`.

## Versioned and translated entities

Set `versioned: true` on `Entity` to add the standard `version_id` primary-key component and
`VersionField`. Do not redeclare the inherited `Entity::$versionId` property.

Set `inheritanceAware: true` and declare the conventional self-reference to enable DAL inheritance:

```php
#[ForeignKey(ReviewDefinition::class, onDelete: OnDelete::Cascade, api: ApiScope::All)]
public ?string $parentId = null;

#[ManyToOne(ReviewDefinition::class, localField: 'parentId')]
public ?ReviewEntity $parent = null;

#[OneToMany(ReviewDefinition::class, referenceField: 'parentId')]
public ?ReviewCollection $children = null;
```

Jetpack validates the shape and compiles it to Shopware's specialized `ParentFkField`,
`ParentAssociationField`, and `ChildrenAssociationField`. On a versioned entity, also declare a
nullable `ReferenceVersion` for `parentId`:

```php
#[ReferenceVersion(ReviewDefinition::class, forField: 'parentId')]
public ?string $parentVersionId = null;
```

Individual values or associations opt into inheritance with `inherited: true`.

For translations, mark fields on the main entity and name a concrete translation definition:

```php
#[Field(api: ApiScope::All, translated: true)]
public string $name;

#[EntityDeclaration(
    name: 'acme_review',
    entity: ReviewEntity::class,
    collection: ReviewCollection::class,
    translationDefinition: ReviewTranslationDefinition::class,
)]
final class ReviewDefinition extends JetpackEntityDefinition
{
}

#[TranslationEntity(parent: ReviewDefinition::class)]
final class ReviewTranslationDefinition extends JetpackTranslationDefinition
{
}
```

The translation table, language key, parent key, timestamps, and version-aware composite parent key are
synthesized into both the DAL definition and the migration snapshot.

## Many-to-many mapping definitions

Mapping tables use a concrete definition too:

```php
#[MappingEntity(
    name: 'acme_review_product',
    source: ReviewDefinition::class,
    target: ProductDefinition::class,
    sourceColumn: 'review_id',
    targetColumn: 'product_id',
    sourceVersionColumn: 'acme_review_version_id',
    targetVersionColumn: 'product_version_id',
)]
final class ReviewProductDefinition extends JetpackMappingDefinition
{
}
```

Reference it with `ManyToMany` on the owning entity. Jetpack generates the composite primary key and
both foreign-key constraints. `ManyToMany::onDelete` defaults to `CASCADE` and must match the mapping's
source-side delete action; Jetpack applies it to the runtime association as well as validating the DDL.
For a versioned side, Shopware requires the mapping column name `<entity_name>_version_id`; Jetpack
validates the source-side name and marks the concrete mapping definition as version-aware.
`OneToMany::onDelete` can explicitly add the corresponding DAL delete flag when the foreign key lives
on the target entity. `Index` may also be used on a mapping definition.

## Snapshot workflow

Generated migrations use the Jetpack runtime. Frosh Jetpack discovers and executes them automatically
through Shopware's plugin lifecycle events; the owning plugin needs no base class or lifecycle override.
See [the migration guide](../migrations/README.md) for execution ordering and guarantees.

For a new entity:

```bash
bin/console frosh:jetpack:validate ReviewPlugin
bin/console frosh:jetpack:entity:diff ReviewPlugin
bin/console frosh:jetpack:entity:generate ReviewPlugin --name=create_reviews
```

Generation writes a reversible Jetpack `Migration` under the bundle's migration directory and appends an
immutable snapshot under `Resources/jetpack/entity-schema/`. The journal is a strictly ordered chain with
per-snapshot fingerprints; Jetpack validates every referenced snapshot, link, and fingerprint before
planning another change. Historical snapshots do not require deleted PHP classes to exist, which allows
table removal to be generated after deleting the definition.

Generated migrations are deterministic for a given plan. Table, column, index, primary-key, and
foreign-key creation/removal are guarded using `information_schema` when the migration is applied. This
runtime guard does not affect the no-database generation guarantee.

Changes that only affect runtime DAL metadata, such as API or inheritance flags, append a snapshot-only
journal entry and do not create an empty migration. Engine, charset, and collation changes are physical
changes and are emitted as destructive table-options operations.

All forward operations are written to `up()` and the inverse dependency order is written to `down()`.
Drops, renames, type/nullability changes, primary-key changes, and replaced constraints stop generation
unless `--allow-destructive` is given. Once generated, those operations run during the next plugin
install/update; there is no separate Shopware destructive-migration phase. Adding a required column to an
existing table is rejected unless the field declares a database default.

The reverse plan restores the previous schema shape, including renamed fields, indexes, foreign keys and
table options. It cannot restore data removed by a dropped table or column, so customize the generated
migration when rollback needs an explicit data-preservation strategy.

Renames must be explicit so they cannot be confused with drop-and-create operations:

```php
#[Field(column: 'new_name', renamedFrom: 'old_name')]
public string $newName;

#[EntityDeclaration(
    name: 'acme_new_table',
    entity: ExampleEntity::class,
    renamedFrom: 'acme_old_table',
)]
```

Keep `renamedFrom` in the declaration after committing the migration; the metadata is idempotent once it
also exists in the latest snapshot.

For existing tables, create a baseline instead of an initial create migration:

```bash
bin/console frosh:jetpack:entity:baseline ReviewPlugin
```

Baseline does not inspect or modify the database. The developer is responsible for confirming that the
existing tables match the declarations.

Use this in CI to catch declaration drift:

```bash
bin/console frosh:jetpack:entity:check ReviewPlugin
```

Commit the PHP declarations, generated migration, new snapshot, and `_journal.json` in one change. Never
hand-edit a snapshot to represent database state; change PHP declarations and regenerate instead.

## Command reference

| Command                         | Purpose                                                    |
| ------------------------------- | ---------------------------------------------------------- |
| `make:entity <bundle> <Entity>` | scaffold entity, typed collection, and concrete definition |
| `entity:diff <bundle>`          | print the snapshot-to-code plan                            |
| `entity:generate <bundle>`      | create a migration and append its snapshot                 |
| `entity:check <bundle>`         | fail if code differs from the latest snapshot              |
| `entity:baseline <bundle>`      | record current declarations without a migration            |

All commands use the `frosh:jetpack:` prefix.
The previous `frosh:jetpack:entity:make` spelling remains an alias. All maker commands and their
file-safety guarantees are documented in the [scaffolding guide](../scaffolding/README.md).
Use `frosh:jetpack:validate [bundle]` to compile entity declarations together with every other Jetpack
definition. It is documented in the [validation guide](../validation/README.md).

## Scope and operational guarantees

The DDL renderer targets MySQL 8 and MariaDB 10.11, matching supported Shopware deployments. Jetpack
does not attempt to detect manual database drift: migration history remains the source of deployed state,
while committed snapshots remain the source of generated state. Always review generated SQL before
shipping it, especially destructive operations and data backfills.

Generated migrations are executed by Jetpack's bundle-scoped migration runner. See
[the reversible migration guide](../migrations/README.md) for lifecycle and history guarantees.
