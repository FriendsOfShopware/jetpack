import type { StorybookConfig } from "@storybook/vue3-vite";
import type { Plugin } from "vite";

function jetpackTwigPlugin(): Plugin {
    return {
        name: "frosh-jetpack-storybook-twig",
        enforce: "pre",
        transform(source, id) {
            if (!id.endsWith(".html.twig")) {
                return null;
            }

            const template = source.replaceAll(
                /\{%\s*(?:block\s+[A-Za-z0-9_]+|endblock)\s*%\}/g,
                "",
            );

            return {
                code: `export default ${JSON.stringify(template)};`,
                map: null,
            };
        },
    };
}

const config: StorybookConfig = {
    stories: ["../stories/**/*.mdx", "../stories/**/*.stories.ts"],
    addons: ["@storybook/addon-docs"],
    framework: {
        name: "@storybook/vue3-vite",
        options: {},
    },
    core: {
        disableTelemetry: true,
    },
    viteFinal(viteConfig) {
        viteConfig.build = {
            ...viteConfig.build,
            target: "esnext",
        };
        viteConfig.esbuild = {
            ...viteConfig.esbuild,
            target: "esnext",
        };
        viteConfig.plugins = [
            ...(viteConfig.plugins ?? []),
            jetpackTwigPlugin(),
        ];

        return viteConfig;
    },
};

export default config;
