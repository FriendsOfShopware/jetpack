import { flushPromises, mount } from '@vue/test-utils';

import JetpackFieldShell from '../internal/field-shell';
import JetpackIcon from '../foundation/icon';
import { JetpackNumberField, JetpackTextField } from './input-fields';
import { JetpackCheckbox } from './check-controls';
import { JetpackMultiSelect, JetpackSearchSelect } from './search-selects';
import JetpackEntityMultiSelect from './entity-multi-select';
import type { EntityRecord, MutableEntityCollection } from '../../../administration-crud/association/association';

const formComponents = {
    'jetpack-field-shell': JetpackFieldShell,
    'jetpack-icon': JetpackIcon,
};

describe('Jetpack UI forms', () => {
    it('uses modelValue and forwards attributes to the native text input', async () => {
        const wrapper = mount(JetpackTextField, {
            props: { modelValue: 'before', label: 'Name' },
            attrs: { autocomplete: 'name', 'data-testid': 'name' },
            global: { components: formComponents },
        });

        const input = wrapper.get('input');
        await input.setValue('after');

        expect(input.attributes('autocomplete')).toBe('name');
        expect(input.attributes('data-testid')).toBe('name');
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['after']);
    });

    it('emits numeric values from a number field', async () => {
        const wrapper = mount(JetpackNumberField, {
            props: { modelValue: 1 },
            global: { components: formComponents },
        });

        await wrapper.get('input').setValue('42');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([42]);
    });

    it('emits booleans from a checkbox', async () => {
        const wrapper = mount(JetpackCheckbox, {
            props: { modelValue: false, label: 'Enabled' },
            global: { components: formComponents },
        });

        await wrapper.get('input').setValue(true);

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([true]);
    });

    it('filters and selects a searchable option', async () => {
        const wrapper = mount(JetpackSearchSelect, {
            props: {
                modelValue: null,
                options: [
                    { value: 'alpha', label: 'Alpha' },
                    { value: 'beta', label: 'Beta' },
                ],
            },
            global: { components: formComponents },
        });

        await wrapper.get('.jetpack-search-select__trigger').trigger('click');
        await wrapper.get('input[type="search"]').setValue('bet');
        await wrapper.get('.jetpack-search-select__option').trigger('click');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['beta']);
    });

    it('keeps multiple selections as an array', async () => {
        const wrapper = mount(JetpackMultiSelect, {
            props: {
                modelValue: ['alpha'],
                options: [
                    { value: 'alpha', label: 'Alpha' },
                    { value: 'beta', label: 'Beta' },
                ],
            },
            global: { components: formComponents },
        });

        await wrapper.get('.jetpack-search-select__trigger').trigger('click');
        await wrapper.findAll('.jetpack-search-select__option')[1].trigger('click');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
            [
                'alpha',
                'beta',
            ],
        ]);

        document.body.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.jetpack-search-select__popover').exists()).toBe(false);
    });

    it('synchronizes a to-many selection with the DAL entity collection', async () => {
        const collection = [
            { id: 'one', name: 'First' },
        ] as MutableEntityCollection;
        collection.getIds = () => collection.map((entity) => entity.id);
        collection.add = (entity: EntityRecord) => collection.push(entity);
        collection.remove = (id: string) => {
            const index = collection.findIndex((entity) => entity.id === id);
            collection.splice(index, 1);
            return true;
        };
        const repository = {
            search: jest.fn().mockResolvedValue([
                { id: 'one', name: 'First' },
                { id: 'two', name: 'Second' },
            ]),
        };
        const wrapper = mount(JetpackEntityMultiSelect, {
            props: {
                modelValue: collection,
                entity: 'product',
                context: { languageId: 'translated-language' },
            },
            global: {
                components: {
                    ...formComponents,
                    'jetpack-multi-select': JetpackMultiSelect,
                },
                provide: {
                    repositoryFactory: {
                        create: () => repository,
                    },
                },
            },
        });
        await flushPromises();

        expect(repository.search).toHaveBeenCalledWith(
            expect.anything(),
            expect.objectContaining({ languageId: 'translated-language' }),
        );

        await wrapper.get('.jetpack-search-select__trigger').trigger('click');
        await wrapper.findAll('.jetpack-search-select__option')[1].trigger('click');

        expect(collection.getIds()).toEqual([
            'one',
            'two',
        ]);
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
            collection,
        ]);
    });
});
