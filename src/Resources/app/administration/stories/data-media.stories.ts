import type { Meta, StoryObj } from "@storybook/vue3-vite";
import { ref } from "vue";

const meta: Meta = {
    title: "Data & Media/Overview",
    parameters: {
        docs: {
            description: {
                component:
                    "Tables, dependency-free SVG charts, media input, previews, and avatars.",
            },
        },
    },
};

export default meta;

type Story = StoryObj;

const columns = [
    { property: "name", label: "Product", sortable: true },
    { property: "active", label: "Active", align: "center" as const },
    {
        property: "stock",
        label: "Stock",
        sortable: true,
        align: "end" as const,
    },
];

const products = [
    { id: "product-1", name: "Mountain backpack", active: true, stock: 42 },
    { id: "product-2", name: "Travel bottle", active: true, stock: 18 },
    { id: "product-3", name: "Wool blanket", active: false, stock: 7 },
];

const previewSource =
    "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='640' height='360' viewBox='0 0 640 360'%3E%3Crect width='640' height='360' fill='%23e7f1ff'/%3E%3Ccircle cx='320' cy='150' r='74' fill='%230870ff'/%3E%3Cpath d='m286 152 25 25 49-54' fill='none' stroke='white' stroke-width='16' stroke-linecap='round' stroke-linejoin='round'/%3E%3Ctext x='320' y='278' text-anchor='middle' font-family='Arial' font-size='28' fill='%23182233'%3EFrosh Jetpack%3C/text%3E%3C/svg%3E";

export const DataTable: Story = {
    args: {
        selectable: true,
        loading: false,
    },
    render: (args) => ({
        setup() {
            return {
                args,
                columns,
                products,
                selected: ref(["product-2"]),
                sort: ref("No sorting selected"),
            };
        },
        template: `
            <div class="jetpack-story">
                <jetpack-data-table
                    v-model="selected"
                    v-bind="args"
                    :columns="columns"
                    :items="products"
                    @sort-change="sort = JSON.stringify($event)"
                >
                    <template #column-active="{ value }">
                        <jetpack-badge :variant="value ? 'positive' : 'neutral'">
                            {{ value ? 'Active' : 'Inactive' }}
                        </jetpack-badge>
                    </template>
                    <template #actions="{ item }">
                        <jetpack-button variant="tertiary" size="small">Edit {{ item.name }}</jetpack-button>
                    </template>
                </jetpack-data-table>
                <pre class="jetpack-story__value">Selected: {{ selected }} · Sort: {{ sort }}</pre>
            </div>
        `,
    }),
};

export const EntityTable: Story = {
    render: () => ({
        setup() {
            return {
                columns,
                selected: ref([]),
            };
        },
        template: `
            <div class="jetpack-story">
                <jetpack-entity-table
                    v-model="selected"
                    entity="product"
                    :columns="columns"
                    selectable
                />
                <pre class="jetpack-story__value">{{ selected }}</pre>
            </div>
        `,
    }),
};

export const Charts: Story = {
    args: {
        type: "bar",
    },
    argTypes: {
        type: {
            control: "inline-radio",
            options: ["bar", "line"],
        },
    },
    render: (args) => ({
        setup() {
            return {
                args,
                points: [
                    { label: "Mon", value: 12 },
                    { label: "Tue", value: 28 },
                    { label: "Wed", value: 19 },
                    { label: "Thu", value: 42 },
                    { label: "Fri", value: 34 },
                ],
            };
        },
        template: `
            <jetpack-card title="Orders this week">
                <jetpack-chart v-bind="args" :points="points" title="Orders" />
            </jetpack-card>
        `,
    }),
};

export const AvatarsAndPreview: Story = {
    render: () => ({
        setup() {
            return { previewSource };
        },
        template: `
            <div class="jetpack-story">
                <div class="jetpack-story__row">
                    <jetpack-avatar name="Ada Lovelace" size="small" />
                    <jetpack-avatar name="Grace Hopper" />
                    <jetpack-avatar name="Margaret Hamilton" size="large" />
                </div>
                <jetpack-media-preview
                    class="jetpack-story__media"
                    :src="previewSource"
                    alt="Frosh Jetpack placeholder"
                    caption="Independent media preview"
                    removable
                />
            </div>
        `,
    }),
};

export const MediaUpload: Story = {
    render: () => ({
        setup() {
            const selectedFile = ref("No file selected");

            return {
                selectedFile,
                selectFile: (file: File | File[]) => {
                    selectedFile.value = Array.isArray(file)
                        ? file.map((item) => item.name).join(", ")
                        : file.name;
                },
            };
        },
        template: `
            <div class="jetpack-story">
                <jetpack-media-upload
                    label="Product media"
                    accept="image/*"
                    description="PNG, JPEG, WebP, or SVG"
                    @update:model-value="selectFile"
                />
                <pre class="jetpack-story__value">{{ selectedFile }}</pre>
            </div>
        `,
    }),
};
