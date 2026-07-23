import template from './textarea.html.twig';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        modelValue: { type: String, required: false, default: '' },
        id: { type: String, required: false, default: null },
        label: { type: String, required: false, default: '' },
        helpText: { type: String, required: false, default: '' },
        error: { type: String, required: false, default: '' },
        placeholder: { type: String, required: false, default: '' },
        rows: { type: Number, required: false, default: 4 },
        disabled: { type: Boolean, required: false, default: false },
        readonly: { type: Boolean, required: false, default: false },
        required: { type: Boolean, required: false, default: false },
    },

    emits: [
        'update:modelValue',
        'change',
        'blur',
        'focus',
    ],
});
