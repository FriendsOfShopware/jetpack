import template from './native-picker.html.twig';

function registerPicker(name: string, pickerType: 'date' | 'color') {
    return Shopware.Component.register(name, {
        template,
        inheritAttrs: false,

        props: {
            modelValue: {
                type: String,
                required: false,
                default: pickerType === 'color' ? '#0870ff' : '',
            },
            id: { type: String, required: false, default: null },
            label: { type: String, required: false, default: '' },
            helpText: { type: String, required: false, default: '' },
            error: { type: String, required: false, default: '' },
            disabled: { type: Boolean, required: false, default: false },
            required: { type: Boolean, required: false, default: false },
            min: { type: String, required: false, default: undefined },
            max: { type: String, required: false, default: undefined },
        },

        emits: [
            'update:modelValue',
            'change',
        ],

        data() {
            return { pickerType };
        },

        methods: {
            onInput(event: Event): void {
                this.$emit('update:modelValue', (event.target as HTMLInputElement).value);
            },
        },
    });
}

export const JetpackDatepicker = registerPicker('jetpack-datepicker', 'date');
export const JetpackColorPicker = registerPicker('jetpack-color-picker', 'color');
