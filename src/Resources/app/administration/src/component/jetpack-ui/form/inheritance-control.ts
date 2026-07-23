import template from './inheritance-control.html.twig';

export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        inherited: { type: Boolean, required: false, default: false },
        label: { type: String, required: false, default: '' },
        inheritLabel: {
            type: String,
            required: false,
            default: 'Use inherited value',
        },
        overwriteLabel: {
            type: String,
            required: false,
            default: 'Edit value',
        },
        disabled: { type: Boolean, required: false, default: false },
    },

    emits: ['update:inherited'],
});
