import template from './tooltip.html.twig';
import type { PropType } from 'vue';
import { createJetpackId } from '../utils';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        text: { type: String, required: true },
        position: {
            type: String as PropType<'top' | 'right' | 'bottom' | 'left'>,
            required: false,
            default: 'top',
        },
    },

    data() {
        return { tooltipId: createJetpackId('tooltip') };
    },
});
