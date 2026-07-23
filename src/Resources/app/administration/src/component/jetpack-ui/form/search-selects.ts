import template from './search-select.html.twig';
import type { PropType } from 'vue';
import type { JetpackOption, JetpackOptionValue } from '../types';

function registerSearchSelect(name: string, multiple: boolean) {
    return Shopware.Component.register(name, {
        template,
        inheritAttrs: false,

        props: {
            modelValue: {
                type: [
                    String,
                    Number,
                    Boolean,
                    Array,
                ] as PropType<JetpackOptionValue | JetpackOptionValue[] | null>,
                required: false,
                default: () => (multiple ? [] : null),
            },
            options: {
                type: Array as PropType<JetpackOption[]>,
                required: false,
                default: () => [],
            },
            id: { type: String, required: false, default: null },
            label: { type: String, required: false, default: '' },
            helpText: { type: String, required: false, default: '' },
            error: { type: String, required: false, default: '' },
            placeholder: {
                type: String,
                required: false,
                default: 'Select an option',
            },
            searchPlaceholder: {
                type: String,
                required: false,
                default: 'Search…',
            },
            emptyText: {
                type: String,
                required: false,
                default: 'No options found',
            },
            disabled: { type: Boolean, required: false, default: false },
            required: { type: Boolean, required: false, default: false },
        },

        emits: [
            'update:modelValue',
            'change',
            'search',
        ],

        data() {
            return {
                multiple,
                open: false,
                searchTerm: '',
                documentClickHandler: null as ((event: MouseEvent) => void) | null,
            };
        },

        mounted() {
            this.documentClickHandler = (event) => this.onDocumentClick(event);
            document.addEventListener('mousedown', this.documentClickHandler);
        },

        beforeUnmount() {
            if (this.documentClickHandler) {
                document.removeEventListener('mousedown', this.documentClickHandler);
            }
        },

        computed: {
            selectedValues(): JetpackOptionValue[] {
                if (multiple) {
                    return Array.isArray(this.modelValue) ? this.modelValue : [];
                }

                return this.modelValue === null || Array.isArray(this.modelValue) ? [] : [this.modelValue];
            },

            selectedOptions(): JetpackOption[] {
                return this.options.filter((option) => this.isSelected(option.value));
            },

            filteredOptions(): JetpackOption[] {
                const term = this.searchTerm.trim().toLocaleLowerCase();

                if (!term) {
                    return this.options;
                }

                return this.options.filter((option) => option.label.toLocaleLowerCase().includes(term));
            },
        },

        methods: {
            isSelected(value: JetpackOptionValue): boolean {
                return this.selectedValues.some((selected) => selected === value);
            },

            toggle(value: JetpackOptionValue): void {
                if (!multiple) {
                    this.commit(value);
                    this.open = false;
                    return;
                }

                const next = this.isSelected(value)
                    ? this.selectedValues.filter((selected) => selected !== value)
                    : [
                          ...this.selectedValues,
                          value,
                      ];

                this.commit(next);
            },

            remove(value: JetpackOptionValue): void {
                this.commit(this.selectedValues.filter((selected) => selected !== value));
            },

            commit(value: JetpackOptionValue | JetpackOptionValue[]): void {
                this.$emit('update:modelValue', value);
                this.$emit('change', value);
            },

            onDocumentClick(event: MouseEvent): void {
                const root = this.$refs.root as HTMLElement;

                if (this.open && !root.contains(event.target as Node)) {
                    this.open = false;
                }
            },
        },
    });
}

export const JetpackSearchSelect = registerSearchSelect('jetpack-search-select', false);
export const JetpackMultiSelect = registerSearchSelect('jetpack-multi-select', true);
