import template from './link.html.twig';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        href: {
            type: String,
            required: false,
            default: null,
        },
        to: {
            type: [
                String,
                Object,
            ],
            required: false,
            default: null,
        },
        target: {
            type: String,
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
        componentTag(): string {
            return this.to ? 'router-link' : 'a';
        },
    },

    methods: {
        onClick(event: MouseEvent): void {
            if (!this.disabled) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
        },
    },
});
