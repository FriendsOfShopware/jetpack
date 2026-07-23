import type { Preview } from "@storybook/vue3-vite";
import { setup } from "@storybook/vue3-vite";

import "./preview.scss";
import { componentsReady, installJetpackStorybook } from "./shopware-bootstrap";

await componentsReady;

setup((app) => {
    installJetpackStorybook(app);
});

const preview: Preview = {
    tags: ["autodocs"],
    parameters: {
        layout: "padded",
        controls: {
            expanded: true,
            sort: "requiredFirst",
            matchers: {
                color: /(background|color)$/i,
                date: /Date$/i,
            },
        },
        options: {
            storySort: {
                order: [
                    "Introduction",
                    "Foundation",
                    "Forms",
                    "Layout & Feedback",
                    "Navigation & Overlays",
                    "Data & Media",
                ],
            },
        },
    },
};

export default preview;
