import { fileURLToPath } from "node:url";
import shopwareAdministration from "../../../../../../../src/Administration/Resources/app/administration/eslint.config.mjs";

const directory = fileURLToPath(new URL(".", import.meta.url));

export default [
    ...shopwareAdministration,
    {
        files: ["src/**/*.ts", "src/**/*.html.twig"],
        languageOptions: {
            parserOptions: {
                project: ["./tsconfig.json"],
                tsconfigRootDir: directory,
            },
        },
        rules: {
            // Jetpack owns its controls; sw-page remains the deliberate Administration shell boundary.
            "sw-deprecation-rules/no-deprecated-components": "off",
            // These rules describe Shopware core's public API and package boundaries, not extension code.
            "sw-core-rules/enforce-async-component-registers": "off",
            "sw-core-rules/require-package-annotation": "off",
            "sw-deprecation-rules/private-feature-declarations": "off",
            // Dynamic labels and the native file drop-zone are accessible, but cannot be resolved statically.
            "vuejs-accessibility/label-has-for": "off",
            "vuejs-accessibility/no-static-element-interactions": "off",
        },
    },
    {
        files: [".storybook/**/*.ts", "stories/**/*.ts"],
        languageOptions: {
            parserOptions: {
                project: ["./tsconfig.storybook.json"],
                tsconfigRootDir: directory,
            },
        },
        rules: {
            "sw-core-rules/require-package-annotation": "off",
            "sw-deprecation-rules/private-feature-declarations": "off",
        },
    },
    {
        files: [".storybook/shopware-bootstrap.ts"],
        rules: {
            // The adapter translates Shopware's legacy component configuration into Vue components.
            "@typescript-eslint/no-unsafe-argument": "off",
            "@typescript-eslint/no-unsafe-assignment": "off",
            "@typescript-eslint/no-unsafe-return": "off",
        },
    },
    {
        files: ["test/**/*.js"],
        rules: {
            "sw-core-rules/require-package-annotation": "off",
        },
    },
];
