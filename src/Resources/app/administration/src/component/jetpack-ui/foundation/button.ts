import template from './button.html.twig';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    emits: ['click'],

    props: {
        variant: {
            type: String,
            required: false,
            default: 'secondary',
            validator: (value: string): boolean =>
                [
                    'primary',
                    'secondary',
                    'tertiary',
                    'critical',
                ].includes(value),
        },
        size: {
            type: String,
            required: false,
            default: 'default',
            validator: (value: string): boolean =>
                [
                    'small',
                    'default',
                    'large',
                ].includes(value),
        },
        type: {
            type: String,
            required: false,
            default: 'button',
            validator: (value: string): boolean =>
                [
                    'button',
                    'submit',
                    'reset',
                ].includes(value),
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
        loading: {
            type: Boolean,
            required: false,
            default: false,
        },
        block: {
            type: Boolean,
            required: false,
            default: false,
        },
        square: {
            type: Boolean,
            required: false,
            default: false,
        },
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
    },

    computed: {
        componentTag(): string {
            if (this.to) {
                return 'router-link';
            }

            return this.href ? 'a' : 'button';
        },

        nativeType(): string | undefined {
            return this.componentTag === 'button' ? this.type : undefined;
        },

        isUnavailable(): boolean {
            return this.disabled || this.loading;
        },
    },

    methods: {
        onClick(event: MouseEvent): void {
            if (this.isUnavailable) {
                event.preventDefault();
                event.stopImmediatePropagation();

                return;
            }

            this.$emit('click', event);
        },
    },
});
