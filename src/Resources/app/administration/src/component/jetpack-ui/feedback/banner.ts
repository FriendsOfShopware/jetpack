import template from './banner.html.twig';
import type { PropType } from 'vue';

type BannerVariant = 'info' | 'positive' | 'warning' | 'critical';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        variant: {
            type: String as PropType<BannerVariant>,
            required: false,
            default: 'info',
        },
        title: { type: String, required: false, default: '' },
        message: { type: String, required: false, default: '' },
        dismissible: { type: Boolean, required: false, default: false },
    },

    emits: ['dismiss'],

    computed: {
        iconName(): string {
            return this.variant === 'positive' ? 'check' : this.variant;
        },
    },
});
