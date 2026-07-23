import template from './check-control.html.twig';
import { createJetpackId, eventChecked } from '../utils';

function registerCheckControl(name: string, appearance: 'checkbox' | 'switch') {
    return Shopware.Component.register(name, {
        template,
        inheritAttrs: false,

        props: {
            modelValue: { type: Boolean, required: false, default: false },
            label: { type: String, required: false, default: '' },
            helpText: { type: String, required: false, default: '' },
            disabled: { type: Boolean, required: false, default: false },
            required: { type: Boolean, required: false, default: false },
        },

        emits: [
            'update:modelValue',
            'change',
        ],

        data() {
            return {
                controlId: createJetpackId(appearance),
                appearance,
            };
        },

        methods: {
            onChange(event: Event): void {
                const value = eventChecked(event);

                this.$emit('update:modelValue', value);
                this.$emit('change', value);
            },
        },
    });
}

export const JetpackCheckbox = registerCheckControl('jetpack-checkbox', 'checkbox');
export const JetpackSwitch = registerCheckControl('jetpack-switch', 'switch');
