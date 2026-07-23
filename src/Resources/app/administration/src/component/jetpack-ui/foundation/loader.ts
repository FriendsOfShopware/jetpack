import template from './loader.html.twig';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        size: {
            type: [
                String,
                Number,
            ],
            required: false,
            default: 32,
        },
        label: {
            type: String,
            required: false,
            default: 'Loading',
        },
    },

    computed: {
        resolvedSize(): string {
            return typeof this.size === 'number' ? `${this.size}px` : this.size;
        },
    },
});
