import template from './icon.html.twig';
import { jetpackIcons } from '../utils';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        name: {
            type: String,
            required: true,
            validator: (value: string): boolean => Object.hasOwn(jetpackIcons, value),
        },
        size: {
            type: [
                String,
                Number,
            ],
            required: false,
            default: 20,
        },
        decorative: {
            type: Boolean,
            required: false,
            default: true,
        },
        label: {
            type: String,
            required: false,
            default: '',
        },
    },

    computed: {
        paths() {
            return jetpackIcons[this.name] ?? [];
        },

        resolvedSize(): string {
            return typeof this.size === 'number' ? `${this.size}px` : this.size;
        },
    },
});
