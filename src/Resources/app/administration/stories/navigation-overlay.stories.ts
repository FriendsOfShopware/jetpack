import type { Meta, StoryObj } from "@storybook/vue3-vite";
import { ref } from "vue";

const meta: Meta = {
    title: "Navigation & Overlays/Overview",
    parameters: {
        docs: {
            description: {
                component:
                    "Controlled navigation, modal, tooltip, popover, and contextual action patterns.",
            },
        },
    },
};

export default meta;

type Story = StoryObj;

export const TabsAndPagination: Story = {
    render: () => ({
        setup() {
            return {
                activeTab: ref("general"),
                page: ref(2),
                tabs: [
                    { name: "general", label: "General" },
                    { name: "advanced", label: "Advanced", badge: "3" },
                    { name: "errors", label: "Errors", error: true },
                    { name: "disabled", label: "Disabled", disabled: true },
                ],
            };
        },
        template: `
            <div class="jetpack-story">
                <jetpack-tabs v-model="activeTab" :tabs="tabs">
                    <jetpack-card compact>Active tab: {{ activeTab }}</jetpack-card>
                </jetpack-tabs>
                <jetpack-pagination v-model="page" :total="250" :limit="25" />
                <pre class="jetpack-story__value">Page {{ page }}</pre>
            </div>
        `,
    }),
};

export const Modal: Story = {
    render: () => ({
        setup() {
            return { open: ref(false) };
        },
        template: `
            <div class="jetpack-story__row">
                <jetpack-button variant="primary" @click="open = true">Open modal</jetpack-button>
                <jetpack-modal v-model="open" title="Confirm operation">
                    This modal closes through Escape, its close button, or the backdrop.
                    <template #footer>
                        <jetpack-button variant="tertiary" @click="open = false">Cancel</jetpack-button>
                        <jetpack-button variant="primary" @click="open = false">Confirm</jetpack-button>
                    </template>
                </jetpack-modal>
            </div>
        `,
    }),
};

export const TooltipAndPopover: Story = {
    render: () => ({
        setup() {
            return { popoverOpen: ref(false) };
        },
        template: `
            <div class="jetpack-story__row">
                <jetpack-tooltip text="Accessible tooltip content">
                    <jetpack-button>Hover or focus</jetpack-button>
                </jetpack-tooltip>
                <jetpack-popover v-model="popoverOpen">
                    <template #trigger="{ toggle }">
                        <jetpack-button @click="toggle">Toggle popover</jetpack-button>
                    </template>
                    <jetpack-stack gap="small">
                        <strong>Popover content</strong>
                        <span>Click outside to close it.</span>
                    </jetpack-stack>
                </jetpack-popover>
            </div>
        `,
    }),
};

export const Menus: Story = {
    render: () => ({
        setup() {
            return {
                selection: ref("Nothing selected"),
                items: [
                    { id: "edit", label: "Edit", icon: "edit" },
                    { id: "duplicate", label: "Duplicate", icon: "plus" },
                    { id: "unavailable", label: "Unavailable", disabled: true },
                    {
                        id: "delete",
                        label: "Delete",
                        icon: "trash",
                        critical: true,
                    },
                ],
            };
        },
        template: `
            <div class="jetpack-story">
                <div class="jetpack-story__row">
                    <jetpack-action-menu :items="items" @select="selection = 'Action: ' + $event.id" />
                    <jetpack-context-menu :items="items" aria-label="Context actions" @select="selection = 'Context: ' + $event.id">
                        <template #trigger="{ toggle }">
                            <jetpack-button @click="toggle">Context menu</jetpack-button>
                        </template>
                    </jetpack-context-menu>
                </div>
                <pre class="jetpack-story__value">{{ selection }}</pre>
            </div>
        `,
    }),
};
