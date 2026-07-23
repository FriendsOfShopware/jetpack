import template from './skeleton.html.twig';
import type { PropType } from 'vue';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        variant: {
            type: String as PropType<'text' | 'rectangle' | 'circle'>,
            required: false,
            default: 'text',
        },
        size: {
            type: String as PropType<'small' | 'default' | 'large'>,
            required: false,
            default: 'default',
        },
    },
});
