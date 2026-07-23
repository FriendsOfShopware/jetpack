import type { Meta, StoryObj } from "@storybook/vue3-vite";

const meta: Meta = {
    title: "Foundation/Overview",
    parameters: {
        docs: {
            description: {
                component:
                    "Native, dependency-free primitives used throughout the Jetpack component catalog.",
            },
        },
    },
};

export default meta;

type Story = StoryObj;

export const Button: Story = {
    args: {
        label: "Save configuration",
        variant: "primary",
        size: "default",
        disabled: false,
        loading: false,
        block: false,
    },
    argTypes: {
        variant: {
            control: "select",
            options: ["primary", "secondary", "tertiary", "critical"],
        },
        size: {
            control: "select",
            options: ["small", "default", "large"],
        },
    },
    render: (args) => ({
        setup() {
            return { args };
        },
        template: `
            <div class="jetpack-story">
                <jetpack-button v-bind="args">{{ args.label }}</jetpack-button>
                <div class="jetpack-story__row">
                    <jetpack-button variant="primary">Primary</jetpack-button>
                    <jetpack-button variant="secondary">Secondary</jetpack-button>
                    <jetpack-button variant="tertiary">Tertiary</jetpack-button>
                    <jetpack-button variant="critical">Critical</jetpack-button>
                    <jetpack-button variant="secondary" square aria-label="Add">
                        <jetpack-icon name="plus" />
                    </jetpack-button>
                </div>
            </div>
        `,
    }),
};

export const Icons: Story = {
    render: () => ({
        setup() {
            return {
                icons: [
                    "check",
                    "close",
                    "plus",
                    "minus",
                    "chevron-down",
                    "chevron-up",
                    "chevron-left",
                    "chevron-right",
                    "search",
                    "info",
                    "warning",
                    "critical",
                    "eye",
                    "eye-off",
                    "trash",
                    "edit",
                    "more",
                    "calendar",
                    "upload",
                    "download",
                ],
            };
        },
        template: `
            <div class="jetpack-story__grid">
                <jetpack-card v-for="icon in icons" :key="icon" compact>
                    <div class="jetpack-story__row">
                        <jetpack-icon :name="icon" :size="24" :decorative="false" :label="icon" />
                        <code>{{ icon }}</code>
                    </div>
                </jetpack-card>
            </div>
        `,
    }),
};

export const LinkAndLoader: Story = {
    args: {
        loadingLabel: "Loading component data",
    },
    render: (args) => ({
        setup() {
            return { args };
        },
        template: `
            <div class="jetpack-story__row">
                <jetpack-link href="https://github.com/FriendsOfShopware" target="_blank">
                    Friends of Shopware
                </jetpack-link>
                <jetpack-link href="#" disabled>Disabled link</jetpack-link>
                <jetpack-loader :label="args.loadingLabel" />
            </div>
        `,
    }),
};
