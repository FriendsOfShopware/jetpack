# Storybook component viewer

Frosh Jetpack includes a standalone Vue 3 Storybook for visually exploring and exercising the
Administration component catalog. It runs against the real component source, SCSS tokens, Twig
templates, and Vue 3 `modelValue` contracts.

[Open the live component catalog](https://friendsofshopware.github.io/jetpack/storybook/){ .md-button .md-button--primary }

## Start the viewer

```bash
cd custom/static-plugins/FroshJetpack/src/Resources/app/administration
npm ci
npm run storybook
```

Open `http://localhost:6007`. The sidebar groups stories into foundation, forms, layout and feedback,
navigation and overlays, and data and media.

## Build static documentation

```bash
npm run storybook:typecheck
npm run storybook:build
```

The static viewer is written to `storybook-static/`. The directory is ignored because it is a
reproducible development artifact. Documentation CI copies the build to `site/storybook/`, so the
Zensical guides and live component catalog are published together on GitHub Pages without including
generated Storybook files in the Shopware plugin package.

## How the standalone environment works

Storybook owns a self-contained TypeScript configuration and local declarations for its Twig imports
and limited Shopware facade. Vite receives an explicit raw esbuild configuration, so neither the
typecheck nor the production build resolves the Shopware Administration `tsconfig.json`. The viewer
can therefore be built from a standalone Frosh Jetpack checkout in documentation CI.

The Storybook bootstrap provides only the Shopware boundaries that the components intentionally use:

- `Shopware.Component.register()` and `wrapComponentConfig()`;
- a compatible Criteria fixture;
- a deterministic repository factory for entity-select and entity-table examples;
- a lightweight router-link renderer.

A Vite transform removes Twig `{% block %}` wrappers while preserving Vue template interpolation. No
Meteor or `sw-*` component is mocked, imported, or reproduced. This ensures the viewer catches
accidental host-component coupling instead of hiding it.

## Adding or changing a component

Update the relevant category under `stories/` and include at least:

1. the normal state;
2. meaningful variants or disabled/loading/error states;
3. interactive `v-model` behavior when the component owns a value;
4. a visible output of emitted/controlled state when useful;
5. accessible labels for icon-only or status controls.

The catalog test still verifies that all component registrations are unique. The production
Storybook build additionally verifies that every story and Twig template can be bundled.
