import template from './frosh-jetpack-field.html.twig';

type Translation = Record<string, string>;
type Option = { label: Translation };
type Field = {
    type: string;
    label: Translation;
    helpText?: Translation;
    placeholder?: Translation;
    required?: boolean;
    min?: number;
    max?: number;
    minLength?: number;
    maxLength?: number;
    options?: Record<string, Option>;
};

Shopware.Component.register('frosh-jetpack-field', {
    template,

    emits: ['update:modelValue'],

    props: {
        field: {
            type: Object,
            required: true,
        },
        modelValue: {
            required: false,
            default: null,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        typedField(): Field {
            return this.field as Field;
        },

        label(): string {
            return this.translate(this.typedField.label);
        },

        helpText(): string | undefined {
            return this.typedField.helpText ? this.translate(this.typedField.helpText) : undefined;
        },

        placeholder(): string | undefined {
            return this.typedField.placeholder ? this.translate(this.typedField.placeholder) : undefined;
        },

        options(): Array<{ value: string; label: string }> {
            return Object.entries(this.typedField.options ?? {}).map(
                ([
                    value,
                    option,
                ]) => ({
                    value,
                    label: this.translate(option.label),
                }),
            );
        },
    },

    methods: {
        updateValue(value: unknown): void {
            this.$emit('update:modelValue', value);
        },

        translate(translations: Translation): string {
            const locale = (Shopware.Context.app.config as { locale?: string }).locale ?? 'en-GB';

            return translations[locale] ?? translations['en-GB'] ?? Object.values(translations)[0] ?? '';
        },
    },
});
