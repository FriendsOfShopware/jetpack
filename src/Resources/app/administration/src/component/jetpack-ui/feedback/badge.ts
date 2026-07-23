import template from './badge.html.twig';
import type { PropType } from 'vue';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        variant: {
            type: String as PropType<'neutral' | 'info' | 'positive' | 'warning' | 'critical'>,
            required: false,
            default: 'neutral',
        },
        size: {
            type: String as PropType<'small' | 'default'>,
            required: false,
            default: 'default',
        },
    },
});
