# Unified Jetpack validation

Use one command to validate every declaration type owned by Frosh Jetpack:

```bash
bin/console frosh:jetpack:validate AcmePlugin
```

Omit the bundle name to check all active Shopware bundles:

```bash
bin/console frosh:jetpack:validate
```

The command checks:

- configuration YAML structure, field defaults, types, and constraints;
- custom field YAML structure, type-specific options, and duplicate field names;
- mail template YAML structure, default locales, DAL entity references, conventional Twig files and
  syntax, and duplicate technical names;
- entity attributes, field mappings, primary keys, associations, indexes, and cross-definition
  uniqueness and references;
- reversible migration discovery, class shape, positive timestamps, and per-bundle timestamp
  uniqueness;
- attributed scheduled-task inventory, with declaration shape, intervals, effective names, and
  collisions rejected during container compilation.

Each feature is checked independently. The command prints one summary table and then reports all
discovered validation errors together, so fixing one invalid file does not merely reveal the next
feature's error on a later run. A validation error returns a non-zero exit code suitable for CI.

Validation reads declarations from code, Shopware's DAL definition registry, and the filesystem. It does
not connect to, inspect, or modify the database, and it never executes migration `up()` or `down()`
methods.

Entity snapshot drift is intentionally separate: use `frosh:jetpack:entity:check <bundle>` when CI must
also verify that committed entity snapshots match the current PHP declarations.
