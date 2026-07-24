# Vision

Frosh Jetpack should make building a Shopware extension feel focused: extension authors spend their
time on the behavior that makes their product useful, while Jetpack handles the repetitive,
version-sensitive integration work around it.

The inspiration is Android Jetpack: not a new application platform, but a cohesive toolkit of
independently useful building blocks with consistent conventions, strong documentation, and a
reliable compatibility boundary.

## The problem we want to solve

A small Shopware feature can require a disproportionate amount of plumbing. Authors repeatedly wire
definitions, persistence, lifecycle hooks, Administration components, validation, and generated
files before they can express the feature itself. Some of that code also changes between Shopware
minor versions, although the extension's intent has not changed.

That has three costs:

- extension code becomes larger and harder to understand;
- compatibility work is duplicated across the ecosystem;
- development patterns depend too much on copied examples and internal platform knowledge.

Jetpack should absorb that accidental complexity once and expose a smaller, documented contract to
every extension.

## Where we want to go

We want Frosh Jetpack to become the dependable development toolkit for Shopware extensions: a
collection of focused APIs that cover common extension infrastructure from the database to the
Administration.

An extension author should be able to:

- describe intent with typed PHP attributes, DTOs, or schema-backed YAML;
- get normal Shopware entities, repositories, routes, scheduled tasks, and Administration modules;
- manage extension-owned state safely across install, update, and uninstall;
- generate deterministic code and migrations without depending on the current database;
- use stable Administration components while Jetpack handles supported host-version differences;
- validate every Jetpack declaration through one CI-friendly entry point;
- adopt one feature without changing the extension's base class or committing to the whole toolkit.

Jetpack should make the common path short without making advanced Shopware behavior inaccessible.

## Product pillars

### Declare extension infrastructure

Configuration, DAL entities, custom fields, mail templates, routes, and scheduled tasks should be
expressed close to the code that owns them. Declarations must be typed where possible, validated
early, and compile to recognizable Shopware concepts.

### Own the complete lifecycle

If Jetpack introduces a resource, it should also define how that resource is installed, updated,
validated, and removed. Ownership must be explicit, merchant changes must be preserved where
appropriate, and destructive operations must require deliberate intent.

### Stabilize Administration development

Extension UI should target one documented `jetpack-*` component and module API instead of tracking
every change in Shopware's internal component library. Jetpack should bring consistent Vue 3
contracts and the current visual language to every supported Shopware version.

### Make safe development the easy path

Scaffolding should support dry runs, detect conflicts, and produce code that remains understandable
after generation. Schema changes should come from committed declarations and snapshots. Validation
errors should be actionable and available before deployment.

### Teach the model, not just the syntax

Every feature needs an outcome-first guide, its lifecycle and compatibility boundaries, realistic
examples, and a clear escape hatch to native Shopware APIs. The component catalog and generated
schemas should be usable as living reference documentation.

## Design principles

### Native underneath

Jetpack is an integration toolkit, not a replacement runtime. Shopware's DAL, Symfony container and
routing, Messenger, plugin lifecycle, and Vue runtime remain the execution model. Existing Shopware
knowledge should transfer in both directions.

### Extension-owned on top

Jetpack-owned persistence and metadata must be separated from mutable Shopware implementation
details when that improves cross-version reliability. Consumer plugins remain regular Shopware
plugins and keep ownership of their declarations and data.

### Explicit over magical

Convenience must not hide security, data loss, network boundaries, or expensive behavior. Route
paths and authentication, destructive migrations, ACL privileges, and similar product decisions
stay visible in consumer code.

### Progressive adoption

Each feature should be valuable on its own. Installing Jetpack must not require a special plugin base
class, a project-wide rewrite, or use of unrelated Jetpack APIs.

### Stable public contracts

Compatibility adapters, generated proxy classes, caches, and service wiring are implementation
details. Attributes, DTOs, interfaces, YAML schemas, commands, and Administration component
contracts form the intentionally small public API.

### Deterministic and testable

Given the same declarations and snapshots, Jetpack should produce the same result. Features should
work in CI without relying on a developer database or a complete Shopware source checkout unless the
native operation itself requires one.

## What Jetpack is not

Frosh Jetpack is not:

- a new ORM, scheduler, router, or plugin framework;
- a way to bypass Shopware's DAL, ACL, validation, or lifecycle rules;
- a compatibility promise for undocumented Shopware internals exposed directly to consumers;
- a collection of business-domain features that belong in individual extensions;
- an excuse to turn every three-line Shopware API into a second abstraction.

Some repetition is useful when it keeps behavior explicit. A Jetpack abstraction earns its place
only when it removes meaningful boilerplate or version risk across many extensions.

## How we choose future features

A proposed feature belongs in Frosh Jetpack when most of these statements are true:

1. Many extension authors solve the same infrastructure problem.
2. The current solution needs repetitive wiring, copied code, or version-specific branches.
3. Jetpack can expose a smaller contract without hiding important behavior.
4. The result can remain compatible across the supported Shopware versions.
5. Ownership, validation, failure behavior, and cleanup can be defined clearly.
6. The feature can be adopted independently and has an escape hatch to native Shopware APIs.
7. We can document and test the complete developer workflow, not only the happy-path API.

This filter matters more than the number of features. The project succeeds when its APIs feel
coherent and trustworthy, not when it wraps the largest possible part of Shopware.

## What success looks like

We are moving in the right direction when extensions contain visibly more product code than
integration plumbing; supported Shopware upgrades are handled primarily inside Jetpack; generated
changes are reviewable and reproducible; and a developer can understand the important runtime
behavior from the extension's own declarations.

The long-term goal is simple: when a Shopware extension needs common infrastructure, authors should
first ask whether Jetpack already provides a dependable building block—and feel confident using it
when it does.
