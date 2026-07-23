# Reversible Jetpack migrations

Jetpack migrations are independent of Shopware's `MigrationStep` runtime. They use their own migration
contract, discovery, lock, and history table while still receiving the regular Doctrine DBAL
`Connection` used by Shopware.

## Define a migration

Put migrations in the bundle's normal migration directory and namespace. They need no service
registration and must be instantiable without constructor arguments:

```bash
bin/console frosh:jetpack:make:migration AcmeReviewPlugin CreateReview
```

The maker creates the timestamped class with empty `up()` and `down()` methods without accessing the
database. See the [scaffolding guide](../scaffolding/README.md) for dry runs and conflict behavior.

```php
<?php declare(strict_types=1);

namespace Acme\ReviewPlugin\Migration;

use Doctrine\DBAL\Connection;
use Frosh\Jetpack\Migration\Migration;

final class Migration1785000000CreateReview extends Migration
{
    public function getCreationTimestamp(): int
    {
        return 1785000000;
    }

    public function up(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `acme_review` (
                `id` BINARY(16) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `acme_review`');
    }
}
```

Timestamps must be positive and unique inside the bundle. Once a migration has been applied, keep its
class and timestamp unchanged: `down()` is loaded from that historical class during uninstall.

Validate migration discovery together with the bundle's other Jetpack definitions before release:

```bash
bin/console frosh:jetpack:validate AcmeReviewPlugin
```

This filesystem-only check verifies migration classes, constructors, and timestamps. It does not run
`up()` or `down()` and does not inspect the database. See the [validation guide](../validation/README.md).

## Automatic plugin lifecycle

No plugin base class, lifecycle override, or migration service registration is required. When Frosh
Jetpack is active, its lifecycle subscriber discovers Jetpack `Migration` classes in the plugin's normal
migration directory and handles them automatically:

- pending `up()` methods run in ascending timestamp order before plugin install and update;
- migrations are preloaded before uninstall, while composer-managed plugin files still exist;
- after the plugin's own uninstall method finishes, applied `down()` methods run in descending timestamp
  order;
- an uninstall with “keep user data” skips both preload and rollback.

Rollback uses a late post-uninstall listener so other normal post-uninstall subscribers can finish before
Jetpack removes the plugin schema. Keeping user data leaves both the schema and migration history intact,
so reinstalling can resume from the same state.

There is no Jetpack plugin base class to extend. Consumer plugins keep extending Shopware's regular
`Plugin` class. Frosh Jetpack only bootstraps its own migrations inside its plugin class because its
event subscriber is unavailable during its first installation; the automatic subscriber excludes only
Frosh Jetpack itself to prevent duplicate lifecycle work.

The public `MigrationRunner` service remains available for non-plugin bundles and custom orchestration.

## Manual CLI execution

Run pending Jetpack migrations without triggering a plugin update:

```bash
bin/console frosh:jetpack:migrate AcmeReviewPlugin
```

To process every active Shopware plugin, opt in explicitly with `--all`:

```bash
bin/console frosh:jetpack:migrate --all
```

The command executes only pending `up()` methods and is safe to repeat. It lists every applied
migration class and reports plugins that are already current. The `--all` mode processes active
plugins in deterministic name order and ignores regular Shopware bundles.

There is intentionally no CLI rollback option. Applied `down()` methods remain tied to destructive
plugin uninstall, where Jetpack can honor Shopware's keep-user-data choice and execute the complete
rollback chain in reverse order.

## Execution guarantees

Migration state is stored in `frosh_jetpack_migration`, keyed by bundle and migration class; Shopware's
core `migration` table is not read or written. A MySQL/MariaDB named lock serializes migration work for
each bundle. The successful `up()` call is recorded only after it returns, and a history row is removed
only after `down()` returns.

Jetpack rejects duplicate timestamps, changed timestamps, deleted applied classes, and a new migration
inserted before the latest applied timestamp. Always append migrations instead of rewriting history.

MySQL and MariaDB implicitly commit most DDL. Therefore an entire migration chain cannot be wrapped in
one reliable transaction. Keep every individual `up()` and `down()` retryable and self-contained, use
existence guards for DDL, and perform large data changes in restartable batches. In particular, `down()`
must not lazily load other plugin files: composer-managed plugin code may already have been removed when
post-uninstall rollback runs. A generated `down()` restores schema shape; it cannot restore rows or
column values destroyed by its corresponding `up()`.

Shopware migrations can coexist during a gradual adoption because Jetpack migration classes are ignored
by Shopware's `MigrationStep` loader. Jetpack `up()` runs on Shopware's pre-install/pre-update event,
before the plugin method and before Shopware applies legacy core migrations, so a new Jetpack migration
must not depend on a legacy migration introduced in the same release. Their histories and rollback
behavior remain separate.
