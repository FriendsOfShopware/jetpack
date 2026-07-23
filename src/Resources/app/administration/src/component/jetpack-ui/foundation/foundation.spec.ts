import { mount } from '@vue/test-utils';

import JetpackButton from './button';
import JetpackIcon from './icon';
import JetpackLoader from './loader';
import JetpackFieldShell from '../internal/field-shell';

describe('Jetpack UI foundation', () => {
    it('forwards native attributes and emits native click events', async () => {
        const wrapper = mount(JetpackButton, {
            attrs: {
                'aria-label': 'Save configuration',
                'data-testid': 'save-button',
            },
            slots: { default: 'Save' },
        });

        await wrapper.get('button').trigger('click');

        expect(wrapper.get('button').attributes('aria-label')).toBe('Save configuration');
        expect(wrapper.get('button').attributes('data-testid')).toBe('save-button');
        expect(wrapper.emitted('click')).toHaveLength(1);
    });

    it('blocks interaction while disabled', async () => {
        const wrapper = mount(JetpackButton, {
            props: { disabled: true },
            slots: { default: 'Save' },
        });

        await wrapper.get('button').trigger('click');

        expect(wrapper.get('button').attributes('disabled')).toBeDefined();
        expect(wrapper.emitted('click')).toBeUndefined();
    });

    it('connects field help and errors to the control', () => {
        const wrapper = mount(JetpackFieldShell, {
            props: {
                id: 'example',
                label: 'Example',
                helpText: 'Helpful context',
                error: 'Required value',
            },
            slots: {
                control: '<input id="example" />',
            },
        });

        expect(wrapper.get('label').attributes('for')).toBe('example');
        expect(wrapper.get('.jetpack-field-shell__help').attributes('id')).toBe('example-help');
        expect(wrapper.get('.jetpack-field-shell__error').attributes('id')).toBe('example-error');
    });

    it('provides accessible labels for meaningful icons and loading states', () => {
        const icon = mount(JetpackIcon, {
            props: { name: 'check', label: 'Complete', decorative: false },
        });
        const loader = mount(JetpackLoader, {
            props: { label: 'Loading configuration' },
        });

        expect(icon.get('svg').attributes('aria-label')).toBe('Complete');
        expect(loader.get('[role="status"]').attributes('aria-label')).toBe('Loading configuration');
    });
});
