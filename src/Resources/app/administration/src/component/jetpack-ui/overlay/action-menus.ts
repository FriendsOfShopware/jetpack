import template from './action-menu.html.twig';
import type { PropType } from 'vue';
import type { JetpackMenuItem } from '../types';

function registerMenu(name: string) {
    return Shopware.Component.register(name, {
        template,

        props: {
            items: {
                type: Array as PropType<JetpackMenuItem[]>,
                required: true,
            },
            ariaLabel: { type: String, required: false, default: 'Actions' },
            position: {
                type: String as PropType<'start' | 'center' | 'end'>,
                required: false,
                default: 'end',
            },
        },

        emits: ['select'],

        data() {
            return { open: false };
        },

        methods: {
            select(item: JetpackMenuItem): void {
                if (item.disabled) {
                    return;
                }

                this.$emit('select', item);
                this.open = false;
            },
        },
    });
}

export const JetpackActionMenu = registerMenu('jetpack-action-menu');
export const JetpackContextMenu = registerMenu('jetpack-context-menu');
