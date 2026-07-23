import template from './input-field.html.twig';
import { eventValue } from '../utils';

function registerInputField(name: string, nativeType: 'text' | 'number' | 'password' | 'email' | 'url') {
    return Shopware.Component.register(name, {
        template,
        inheritAttrs: false,

        props: {
            modelValue: {
                type: [
                    String,
                    Number,
                ],
                required: false,
                default: '',
            },
            id: {
                type: String,
                required: false,
                default: null,
            },
            label: {
                type: String,
                required: false,
                default: '',
            },
            helpText: {
                type: String,
                required: false,
                default: '',
            },
            error: {
                type: String,
                required: false,
                default: '',
            },
            placeholder: {
                type: String,
                required: false,
                default: '',
            },
            disabled: {
                type: Boolean,
                required: false,
                default: false,
            },
            readonly: {
                type: Boolean,
                required: false,
                default: false,
            },
            required: {
                type: Boolean,
                required: false,
                default: false,
            },
            min: {
                type: [
                    String,
                    Number,
                ],
                required: false,
                default: undefined,
            },
            max: {
                type: [
                    String,
                    Number,
                ],
                required: false,
                default: undefined,
            },
            step: {
                type: [
                    String,
                    Number,
                ],
                required: false,
                default: undefined,
            },
        },

        emits: [
            'update:modelValue',
            'change',
            'blur',
            'focus',
        ],

        data() {
            return {
                passwordVisible: false,
            };
        },

        computed: {
            resolvedType(): string {
                if (nativeType === 'password' && this.passwordVisible) {
                    return 'text';
                }

                return nativeType;
            },
        },

        methods: {
            normalizedValue(event: Event): string | number | null {
                const value = eventValue(event);

                if (nativeType !== 'number') {
                    return value;
                }

                return value === '' ? null : Number(value);
            },

            onInput(event: Event): void {
                this.$emit('update:modelValue', this.normalizedValue(event));
            },
        },
    });
}

export const JetpackTextField = registerInputField('jetpack-text-field', 'text');
export const JetpackNumberField = registerInputField('jetpack-number-field', 'number');
export const JetpackPasswordField = registerInputField('jetpack-password-field', 'password');
export const JetpackEmailField = registerInputField('jetpack-email-field', 'email');
export const JetpackUrlField = registerInputField('jetpack-url-field', 'url');
