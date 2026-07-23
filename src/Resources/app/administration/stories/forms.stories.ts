import type { Meta, StoryObj } from "@storybook/vue3-vite";
import { ref } from "vue";

const meta: Meta = {
    title: "Forms/Overview",
    parameters: {
        docs: {
            description: {
                component:
                    "Vue 3 modelValue fields with normalized values and native attribute forwarding.",
            },
        },
    },
};

export default meta;

type Story = StoryObj;

const options = [
    { value: "standard", label: "Standard" },
    { value: "express", label: "Express" },
    { value: "pickup", label: "Pickup", disabled: true },
];

export const TextFields: Story = {
    args: {
        disabled: false,
        required: false,
    },
    render: (args) => ({
        setup() {
            return {
                args,
                text: ref("Jetpack"),
                email: ref("dev@example.com"),
                url: ref("https://example.com"),
                password: ref("secret"),
                amount: ref(42),
                description: ref("A multiline value"),
            };
        },
        template: `
            <div class="jetpack-story">
                <div class="jetpack-story__grid">
                    <jetpack-text-field v-model="text" v-bind="args" label="Text" autocomplete="organization" />
                    <jetpack-email-field v-model="email" v-bind="args" label="Email" autocomplete="email" />
                    <jetpack-url-field v-model="url" v-bind="args" label="URL" autocomplete="url" />
                    <jetpack-password-field v-model="password" v-bind="args" label="Password" autocomplete="current-password" />
                    <jetpack-number-field v-model="amount" v-bind="args" label="Amount" :min="0" :max="100" />
                    <jetpack-textarea v-model="description" v-bind="args" label="Description" :rows="3" />
                </div>
                <pre class="jetpack-story__value">{{ { text, email, url, password, amount, description } }}</pre>
            </div>
        `,
    }),
};

export const ChoiceFields: Story = {
    render: () => ({
        setup() {
            return {
                enabled: ref(true),
                confirmed: ref(false),
                delivery: ref("standard"),
                searchable: ref("express"),
                features: ref(["standard"]),
                options,
            };
        },
        template: `
            <div class="jetpack-story">
                <div class="jetpack-story__row">
                    <jetpack-switch v-model="enabled" label="Enabled" />
                    <jetpack-checkbox v-model="confirmed" label="Confirmed" />
                </div>
                <div class="jetpack-story__grid">
                    <jetpack-select v-model="delivery" :options="options" label="Native select" />
                    <jetpack-search-select v-model="searchable" :options="options" label="Search select" />
                    <jetpack-multi-select v-model="features" :options="options" label="Multi-select" />
                </div>
                <pre class="jetpack-story__value">{{ { enabled, confirmed, delivery, searchable, features } }}</pre>
            </div>
        `,
    }),
};

export const EntitySelect: Story = {
    render: () => ({
        setup() {
            return { productId: ref(null) };
        },
        template: `
            <div class="jetpack-story">
                <jetpack-entity-select
                    v-model="productId"
                    entity="product"
                    label="Product"
                    help-text="Uses a deterministic Storybook repository fixture"
                />
                <pre class="jetpack-story__value">{{ productId }}</pre>
            </div>
        `,
    }),
};

export const SpecializedFields: Story = {
    render: () => ({
        setup() {
            return {
                date: ref("2026-07-22"),
                color: ref("#0870ff"),
                inherited: ref(false),
                inheritedValue: ref("Inherited or local value"),
                richText: ref("<p><strong>Editable</strong> HTML content</p>"),
            };
        },
        template: `
            <div class="jetpack-story">
                <div class="jetpack-story__grid">
                    <jetpack-datepicker v-model="date" label="Release date" />
                    <jetpack-color-picker v-model="color" label="Accent color" />
                </div>
                <jetpack-inheritance-control v-model:inherited="inherited" label="Translated label">
                    <jetpack-text-field v-model="inheritedValue" :disabled="inherited" />
                </jetpack-inheritance-control>
                <jetpack-rich-text-editor v-model="richText" label="Mail content" />
                <pre class="jetpack-story__value">{{ { date, color, inherited, inheritedValue, richText } }}</pre>
            </div>
        `,
    }),
};
