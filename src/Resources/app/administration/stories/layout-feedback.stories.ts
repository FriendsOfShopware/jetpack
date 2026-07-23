import type { Meta, StoryObj } from "@storybook/vue3-vite";

const meta: Meta = {
    title: "Layout & Feedback/Overview",
    parameters: {
        docs: {
            description: {
                component:
                    "Responsive layout primitives and application feedback with fixed Jetpack semantic tokens.",
            },
        },
    },
};

export default meta;

type Story = StoryObj;

export const CardsAndLayout: Story = {
    render: () => ({
        template: `
            <div class="jetpack-story">
                <jetpack-card title="Card title" subtitle="Cards provide header, action, content, and footer slots.">
                    <template #actions>
                        <jetpack-button size="small">Action</jetpack-button>
                    </template>
                    <jetpack-grid :columns="2">
                        <jetpack-text-field label="First column" model-value="Grid content" />
                        <jetpack-text-field label="Second column" model-value="Responsive content" />
                    </jetpack-grid>
                    <template #footer>
                        <jetpack-stack direction="horizontal" gap="small" align="center">
                            <jetpack-button variant="tertiary">Cancel</jetpack-button>
                            <jetpack-button variant="primary">Save</jetpack-button>
                        </jetpack-stack>
                    </template>
                </jetpack-card>
            </div>
        `,
    }),
};

export const BannersAndBadges: Story = {
    render: () => ({
        template: `
            <div class="jetpack-story">
                <jetpack-banner variant="info" title="Information">A neutral explanation for the user.</jetpack-banner>
                <jetpack-banner variant="positive" title="Saved">The changes were saved successfully.</jetpack-banner>
                <jetpack-banner variant="warning" title="Review required">Check this configuration before publishing.</jetpack-banner>
                <jetpack-banner variant="critical" title="Could not save" dismissible>An actionable error message.</jetpack-banner>
                <div class="jetpack-story__row">
                    <jetpack-badge>Neutral</jetpack-badge>
                    <jetpack-badge variant="info">Info</jetpack-badge>
                    <jetpack-badge variant="positive">Positive</jetpack-badge>
                    <jetpack-badge variant="warning">Warning</jetpack-badge>
                    <jetpack-badge variant="critical">Critical</jetpack-badge>
                </div>
            </div>
        `,
    }),
};

export const LoadingStates: Story = {
    args: {
        progress: 64,
        indeterminate: false,
    },
    render: (args) => ({
        setup() {
            return { args };
        },
        template: `
            <div class="jetpack-story">
                <jetpack-progress
                    :value="args.progress"
                    :indeterminate="args.indeterminate"
                    label="Import progress"
                    show-value
                />
                <jetpack-stack gap="small">
                    <jetpack-skeleton size="small" />
                    <jetpack-skeleton />
                    <jetpack-skeleton size="large" />
                    <jetpack-skeleton variant="rectangle" />
                </jetpack-stack>
            </div>
        `,
    }),
};

export const EmptyState: Story = {
    render: () => ({
        template: `
            <jetpack-card>
                <jetpack-empty-state
                    title="No extensions yet"
                    description="Create the first item to see it here."
                    icon="plus"
                >
                    <jetpack-button variant="primary">Create extension</jetpack-button>
                </jetpack-empty-state>
            </jetpack-card>
        `,
    }),
};
