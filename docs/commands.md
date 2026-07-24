# Command reference

All commands run through the Shopware console:

```bash
bin/console <command>
```

Use an active Shopware bundle name such as `AcmeReviewPlugin` where the signatures below show
`<bundle>`.

## Validate and operate

| Command | Purpose | Side effects |
| --- | --- | --- |
| `frosh:jetpack:validate [bundle]` | Aggregate configuration, custom-field, mail-template, entity, migration, and scheduled-task errors | Reads declarations; no database writes |
| `frosh:jetpack:migrate <plugin>` | Apply pending Jetpack migrations for one active plugin | Writes the database and migration history |
| `frosh:jetpack:migrate --all` | Apply pending migrations for every active plugin | Writes the database and migration history |

The migrate command requires either one plugin name or `--all`; it rejects an implicit all-plugin
run. Rollback is handled by a destructive plugin uninstall, not a public CLI command. See
[reversible migrations](migrations/README.md).

## Entity schema workflow

| Command | Purpose |
| --- | --- |
| `frosh:jetpack:entity:diff <bundle>` | Show changes between current PHP declarations and the latest committed snapshot |
| `frosh:jetpack:entity:generate <bundle> [--name=<name>]` | Write the next snapshot and, when needed, a reversible migration |
| `frosh:jetpack:entity:check <bundle>` | Fail when declarations differ from the latest snapshot; intended for CI |
| `frosh:jetpack:entity:baseline <bundle>` | Record the current declaration as the initial snapshot without generating a migration |

All four commands are database-independent. Generation refuses destructive operations unless the
reviewer opts in explicitly:

```bash
bin/console frosh:jetpack:entity:generate \
    AcmeReviewPlugin \
    --name=remove_legacy_rating \
    --allow-destructive
```

Use `baseline` only when adopting snapshots for a schema that already exists. For normal changes,
follow the [entity snapshot workflow](entities/README.md#snapshot-workflow).

## Safe makers

| Command | Generated starting point | Useful options |
| --- | --- | --- |
| `frosh:jetpack:make:entity <bundle> <Entity>` | Entity, definition, and typed collection | `--table=<name>`, `--dry-run` |
| `frosh:jetpack:make:migration <bundle> <Migration>` | Empty reversible migration | `--dry-run` |
| `frosh:jetpack:make:scheduled-task <bundle> <Task>` | One attributed scheduled-task service | `--interval=<seconds>`, `--name=<name>`, `--reschedule-on-failure`, `--dry-run` |
| `frosh:jetpack:make:command <bundle> <Command>` | Autoconfigured Symfony command | `--name=<name>`, `--description=<text>`, `--dry-run` |
| `frosh:jetpack:make:cms-element <bundle> <Element> --entity=<dal_name>` | Declarative Admin registration and snippet, DAL-backed resolver, and Storefront Twig | `--name=<name>`, `--field=<key>`, `--definition=<class>`, `--label-property=<property>`, `--dry-run` |

Every maker preflights the complete plan, parses generated PHP, treats byte-identical files as
unchanged, refuses to overwrite different content, and avoids leaving a partial scaffold. There is no
force option. See [scaffolding safety](scaffolding/README.md#dry-runs-and-file-safety).

The CMS element maker creates a standalone Administration module without changing an existing
entrypoint. Import the generated `./cms-element/<name>` module from the consumer's `main.js` or
`main.ts`.

Discover the live signatures in your installed version:

```bash
bin/console list frosh:jetpack
bin/console help frosh:jetpack:make:scheduled-task
bin/console help frosh:jetpack:make:cms-element
```

## Related Shopware commands

Attributed tasks remain normal Shopware scheduled tasks:

```bash
bin/console scheduled-task:register
bin/console scheduled-task:list
bin/console scheduled-task:run-single <task-name>
```

## Build these docs

The documentation site uses Zensical as an isolated `uvx` tool, so it does not add a Python
environment to the plugin:

```bash
uvx zensical@0.0.51 serve --open
uvx zensical@0.0.51 build --clean --strict
```

The explicit version keeps local and CI rendering reproducible. The production build writes `site/`.
Strict mode fails on documentation warnings and is also used by the GitHub Pages workflow.
