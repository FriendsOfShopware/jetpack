import template from './avatar.html.twig';
import type { PropType } from 'vue';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        src: { type: String, required: false, default: '' },
        name: { type: String, required: false, default: '' },
        label: { type: String, required: false, default: '' },
        size: {
            type: String as PropType<'small' | 'default' | 'large'>,
            required: false,
            default: 'default',
        },
    },

    data() {
        return { imageFailed: false };
    },

    computed: {
        initials(): string {
            return (
                this.name
                    .trim()
                    .split(/\s+/)
                    .slice(0, 2)
                    .map((part) => part.charAt(0).toLocaleUpperCase())
                    .join('') || '?'
            );
        },
    },

    watch: {
        src(): void {
            this.imageFailed = false;
        },
    },
});
