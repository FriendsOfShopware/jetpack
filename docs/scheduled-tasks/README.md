# Scheduled tasks

> Status: Implemented. The public API targets Shopware 6.6 and 6.7 while delegating execution and
> persistence to Shopware's scheduled-task runtime.

Frosh Jetpack reduces a Shopware scheduled task from a message class plus a handler class to one
ordinary, autowired service. Shopware remains responsible for persistence, scheduling, queueing,
status transitions, retries, CLI commands, and plugin lifecycle registration.

## Consumer API

Create the one-file starting point with:

```bash
bin/console frosh:jetpack:make:scheduled-task AcmeReview CleanupExpiredReviews --interval=300
```

The maker uses an explicit stable task name and supports dry runs. See the
[scaffolding guide](../scaffolding/README.md) for all options and file-safety guarantees.

```php
<?php declare(strict_types=1);

namespace Acme\Review\ScheduledTask;

use Frosh\Jetpack\Attribute\AsScheduledTask;
use Shopware\Core\Framework\Context;

#[AsScheduledTask(interval: 300)]
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

The class is a normal Symfony service, so constructor injection works as usual. It does not extend a
Shopware or Jetpack base class and the plugin keeps extending Shopware's regular `Plugin` class. When
the consumer namespace is already loaded through an autowired and autoconfigured service resource,
the PHP class is the only task-specific file or service declaration.

The public attribute is deliberately small:

```php
namespace Frosh\Jetpack\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsScheduledTask
{
    public function __construct(
        public int $interval,
        public ?string $name = null,
        public bool $rescheduleOnFailure = false,
    ) {
    }
}
```

- `interval` is the default interval in seconds and must be at least `1`.
- `name` overrides the task's operational name. When omitted, Jetpack derives a deterministic name
  from the fully qualified service class by converting namespace separators to dots and CamelCase to
  snake case. The example becomes
  `acme.review.scheduled_task.cleanup_expired_reviews`.
- `rescheduleOnFailure` maps directly to Shopware's scheduled-task failure behavior. The default is
  `false`, which marks the task failed and lets the exception reach Messenger. `true` logs the
  exception and schedules the next execution using Shopware's normal behavior.

The attributed service must be concrete and expose exactly:

```php
public function __invoke(Context $context): void;
```

Jetpack creates a fresh `Context::createCLIContext()` for every execution. A sales-channel context is
not constructed. Dependencies and task inputs belong in constructor-injected services or persistent
storage, not in the scheduled message.

An explicit name is recommended when external operations call Shopware's task commands by name or
when the PHP class may be renamed:

```php
#[AsScheduledTask(
    interval: 300,
    name: 'acme_review.cleanup_expired_reviews',
    rescheduleOnFailure: true,
)]
```

Names must contain at least two vendor-prefixed dot-separated segments, be no longer than 255
characters, and match `^[a-z][a-z0-9_-]*(?:\.[a-z][a-z0-9_-]*)+$`. Effective names must be unique
across Jetpack scheduled tasks and must not collide with a resolvable native Shopware task name.

## Why Jetpack needs internal adapters

Shopware intentionally has two classes for one scheduled task:

1. A message extends `ScheduledTask`. Its constructor is final and empty, and its static methods
   provide the name, interval, and failure policy.
2. A handler extends `ScheduledTaskHandler`. It receives dependencies and owns Shopware's running,
   failed, and rescheduled state transitions.

One consumer class cannot extend both inheritance trees. Reusing one generic message for all
attributed tasks also does not work because Shopware's `scheduled_task` table has a unique constraint
on `scheduled_task_class`. Replacing Shopware's scheduler with a Jetpack table would lose its status,
CLI, lifecycle, queue, and merchant interval behavior.

Jetpack therefore keeps the two core roles internally while exposing only the attributed service
to consumers.

## Internal architecture

```text
CleanupExpiredReviews (consumer service)
        │
        │ invoked with a CLI Context
        ▼
AttributedScheduledTaskHandler (one internal service definition per task)
        ▲
        │ Messenger handles
        │
Generated ScheduledTask proxy (one deterministic class per task)
        ▲
        │ registered, queued, and persisted by
        │
Shopware TaskRegistry and TaskScheduler
```

### Discovery

`FroshJetpack::build()` registers an internal compiler pass. The pass inspects registered Symfony
services for `#[AsScheduledTask]`; it does not scan arbitrary PHP files. This keeps service ownership
and constructor injection consistent with Symfony.

For every attributed service, the pass creates an immutable internal descriptor containing:

- the consumer service ID and class;
- the effective name and default interval;
- the failure policy;
- a deterministic generated message class name;
- the reflected source file, used to resolve the owning Shopware bundle.

Invalid declarations fail container compilation with the consumer class and property in the error.
This avoids booting a system whose scheduler cannot dispatch a declared task.

### Generated message proxy

Jetpack generates one small PHP class per descriptor into the Symfony cache. Each class extends
Shopware's `ScheduledTask` and implements only:

- `getTaskName()`;
- `getDefaultInterval()`;
- `shouldRescheduleOnFailure()`.

The generated class identity is a stable hash of the consumer class and effective task name. The
interval and failure flag are not part of the identity. Consequently:

- changing the interval updates Shopware's default while preserving a merchant-modified interval;
- changing the failure policy updates the same registered task;
- changing the class or effective name removes the old core task and registers a new one, resetting
  its schedule and any merchant interval override.

Generated source uses fixed templates plus `var_export()` values; no consumer expression is
evaluated. The proxy methods read an internal metadata map that is replaced whenever the generated
file is loaded. This matters when Shopware rebuilds its kernel in the same PHP process: the stable PHP
class remains loaded, while a changed interval or failure policy still takes effect immediately.

An aggregate generated file is written atomically below `%kernel.cache_dir%` and loaded during Frosh
Jetpack bundle boot so scheduler and worker processes can resolve the persisted proxy class before
instantiating it. The generated namespace and class names are internal implementation details and
must not be referenced by consumers.

### Service wiring

The compiler pass registers two internal service definitions per descriptor:

1. The generated proxy is tagged `shopware.scheduled.task`.
2. An `AttributedScheduledTaskHandler` extends Shopware's `ScheduledTaskHandler`, receives
   `scheduled_task.repository`, `logger`, and the consumer service, and is tagged
   `messenger.message_handler` with the generated proxy as its explicit `handles` value.

The adapter's `run()` method calls the consumer service with a fresh CLI `Context`. Extending the core
handler is important: it leaves duplicate-execution protection, running/failed states, exception
logging, retry behavior, and rescheduling in Shopware.

The handler constructor always passes both repository and logger to its parent. That is compatible
with Shopware 6.6, where the logger was optional, and Shopware 6.7, where it is required. The proxy
uses only the `ScheduledTask` static contract shared by both versions.

### Lifecycle

Jetpack does not add another lifecycle subscriber or database table. Because every generated proxy
uses the normal `shopware.scheduled.task` tag, Shopware's existing lifecycle subscriber registers and
removes it on plugin activation, update, and deactivation. The normal commands continue to work:

```bash
bin/console scheduled-task:register
bin/console scheduled-task:list
bin/console scheduled-task:run-single acme_review.cleanup_expired_reviews
```

The consumer plugin does not call Jetpack from `install()`, `update()`, `activate()`, or
`deactivate()`.

## Validation and diagnostics

The unified command includes a `Scheduled tasks` row:

```bash
bin/console frosh:jetpack:validate AcmePlugin
```

It reports the number of attributed tasks owned by the selected bundle. Declaration validation runs
during container compilation and checks:

- a concrete, registered service class with exactly one non-repeatable attribute;
- a public `__invoke(Context $context): void` method;
- a positive interval;
- the name syntax and 255-character limit;
- unique effective names, including collisions with resolvable native Shopware task metadata;
- a resolvable source file used for bundle ownership;
- deterministic, syntactically valid generated proxy source.

An invalid declaration prevents the container from compiling because it cannot safely be registered.
The command remains useful in CI for bundle-scoped inventory and one consolidated Jetpack report.
Neither validation path accesses or modifies the database.

Diagnostics use the prefix `[Frosh Jetpack Scheduled Task]` and name the consumer class, for example:

```text
[Frosh Jetpack Scheduled Task] Acme\Review\Cleanup: interval must be at least 1 second.
```

## Public and internal surface

The compatibility promise consists of:

- `Frosh\Jetpack\Attribute\AsScheduledTask` and its constructor arguments;
- the `public function __invoke(Context $context): void` consumer contract;
- the effective-name derivation and interval/failure semantics documented here.

The descriptor registry, compiler pass, source generator, runtime metadata map, generated proxy
classes, handler adapter, cache paths, service IDs, and tags created by Jetpack are internal.
Shopware's existing task names, table schema, APIs, and commands remain outside Jetpack's public
surface.

## Automated coverage

The test suite covers:

- Attribute defaults and named arguments.
- Deterministic name normalization, validation, Jetpack collisions, and native-task collisions.
- Declaration validation for interval, class shape, and `__invoke()` signature.
- Stable proxy identity across interval/failure changes and a new identity after a name change.
- Same-process metadata refresh while a stable generated proxy class is already loaded.
- Generated source loading and static method values.
- Compiler-pass service definitions and exact Shopware/Messenger tags.
- Handler adapter forwarding one CLI `Context` to the consumer service.
- Sorted registry inventory and bundle ownership filtering.
- The scheduled-task row in the unified validation command.

The adapter deliberately calls only the `ScheduledTask`, `ScheduledTaskHandler`, task tag, Messenger
tag, repository, logger, and CLI-context contracts shared by Shopware 6.6 and 6.7. A release matrix
should additionally exercise a small consumer plugin on the latest supported 6.6 and 6.7 minors:

- A consumer plugin contains one attributed, constructor-injected service and no message/handler
  pair.
- Container compilation generates and loads its proxy in a separate PHP process.
- Plugin activation and `scheduled-task:register` create the expected core row.
- The queued proxy reaches only its matching consumer service.
- Successful execution reschedules through Shopware.
- Failure marks the task failed when `rescheduleOnFailure` is false and reschedules it when true.
- An interval update changes an untouched default but preserves a merchant-modified run interval.
- Plugin deactivation removes the generated task through Shopware's lifecycle behavior.
- The unified validation command reports all and bundle-scoped task counts.

## Deliberate boundaries

Dynamic enable predicates, cron expressions, overlap policies beyond Shopware's own state machine,
per-sales-channel fan-out, and synchronous execution are intentionally deferred until there is a
concrete use case.
