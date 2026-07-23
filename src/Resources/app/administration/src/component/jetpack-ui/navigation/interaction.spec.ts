import { mount } from '@vue/test-utils';

import JetpackButton from '../foundation/button';
import JetpackIcon from '../foundation/icon';
import JetpackPagination from './pagination';
import JetpackModal from '../overlay/modal';
import JetpackPopover from '../overlay/popover';
import { JetpackActionMenu } from '../overlay/action-menus';

const components = {
    'jetpack-button': JetpackButton,
    'jetpack-icon': JetpackIcon,
    'jetpack-popover': JetpackPopover,
};

describe('Jetpack UI interactions', () => {
    it('emits controlled pagination changes', async () => {
        const wrapper = mount(JetpackPagination, {
            props: { modelValue: 1, total: 100, limit: 25 },
            global: { components },
        });

        await wrapper.get('[aria-label="Next page"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([[2]]);
        expect(wrapper.emitted('change')).toEqual([[2]]);
    });

    it('closes an open modal with Escape', async () => {
        const wrapper = mount(JetpackModal, {
            props: { modelValue: true, title: 'Confirm' },
            global: {
                components,
                stubs: { teleport: true },
            },
        });

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('opens an action menu and emits the selected item', async () => {
        const item = { id: 'edit', label: 'Edit', icon: 'edit' };
        const wrapper = mount(JetpackActionMenu, {
            props: { items: [item] },
            global: { components },
        });

        await wrapper.get('[aria-label="Actions"]').trigger('click');
        await wrapper.get('[role="menuitem"]').trigger('click');

        expect(wrapper.emitted('select')).toEqual([[item]]);
    });
});
