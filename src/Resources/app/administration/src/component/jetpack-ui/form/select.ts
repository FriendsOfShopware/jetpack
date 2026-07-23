import template from './select.html.twig';
import type { PropType } from 'vue';
import type { JetpackOption, JetpackOptionValue } from '../types';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        modelValue: {
            type: [
                String,
                Number,
                Boolean,
            ] as PropType<JetpackOptionValue>,
            required: false,
            default: '',
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
        placeholder: { type: String, required: false, default: '' },
        disabled: { type: Boolean, required: false, default: false },
        required: { type: Boolean, required: false, default: false },
    },

    emits: [
        'update:modelValue',
        'change',
    ],

    methods: {
        onChange(event: Event): void {
            const target = event.target as HTMLSelectElement;
            const option = this.options.find((candidate) => String(candidate.value) === target.value);
            const value = option?.value ?? target.value;

            this.$emit('update:modelValue', value);
            this.$emit('change', value);
        },
    },
});
