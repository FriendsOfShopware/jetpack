import { mount } from '@vue/test-utils';

import JetpackDataTable from './data-table';
import JetpackChart from './chart';
import JetpackAvatar from '../media/avatar';

describe('Jetpack UI data and media', () => {
    it('renders nested cell values and emits row selection', async () => {
        const wrapper = mount(JetpackDataTable, {
            props: {
                items: [{ id: 'one', nested: { name: 'First' } }],
                columns: [{ property: 'nested.name', label: 'Name' }],
                modelValue: [],
                selectable: true,
            },
            global: {
                stubs: {
                    'jetpack-icon': true,
                    'jetpack-loader': true,
                },
            },
        });

        expect(wrapper.text()).toContain('First');
        await wrapper.findAll('input[type="checkbox"]')[1].setValue(true);

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['one']]);
    });

    it('emits sorting without mutating rows', async () => {
        const wrapper = mount(JetpackDataTable, {
            props: {
                items: [{ id: 'one', name: 'First' }],
                columns: [{ property: 'name', label: 'Name', sortable: true }],
                sortBy: 'name',
                sortDirection: 'asc',
            },
            global: { stubs: { 'jetpack-icon': true, 'jetpack-loader': true } },
        });

        await wrapper.get('.jetpack-data-table__sort').trigger('click');

        expect(wrapper.emitted('sort-change')).toEqual([
            [{ property: 'name', direction: 'desc' }],
        ]);
    });

    it('renders an accessible line chart', () => {
        const wrapper = mount(JetpackChart, {
            props: {
                type: 'line',
                title: 'Orders',
                points: [
                    { label: 'Mon', value: 2 },
                    { label: 'Tue', value: 5 },
                ],
            },
        });

        expect(wrapper.get('svg').attributes('aria-label')).toBe('Orders');
        expect(wrapper.get('polyline').attributes('points')).not.toBe('');
    });

    it('falls back to initials when an avatar has no image', () => {
        const wrapper = mount(JetpackAvatar, {
            props: { name: 'Ada Lovelace' },
        });

        expect(wrapper.text()).toContain('AL');
        expect(wrapper.get('[role="img"]').attributes('aria-label')).toBe('Ada Lovelace');
    });
});
