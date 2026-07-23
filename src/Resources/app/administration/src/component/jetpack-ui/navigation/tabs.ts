import template from './tabs.html.twig';
import type { PropType } from 'vue';
import type { JetpackTabItem } from '../types';
import { createJetpackId } from '../utils';

export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        modelValue: { type: String, required: true },
        tabs: { type: Array as PropType<JetpackTabItem[]>, required: true },
        ariaLabel: { type: String, required: false, default: 'Tabs' },
    },

    emits: [
        'update:modelValue',
        'change',
    ],

    data() {
        return { tabsId: createJetpackId('tabs') };
    },

    methods: {
        select(name: string): void {
            this.$emit('update:modelValue', name);
            this.$emit('change', name);
        },
    },
});
