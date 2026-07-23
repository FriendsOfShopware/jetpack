import template from './progress.html.twig';
import { clamp } from '../utils';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        value: { type: Number, required: false, default: 0 },
        label: { type: String, required: false, default: '' },
        showValue: { type: Boolean, required: false, default: false },
        indeterminate: { type: Boolean, required: false, default: false },
    },

    computed: {
        normalizedValue(): number {
            return clamp(this.value, 0, 100);
        },
    },
});
