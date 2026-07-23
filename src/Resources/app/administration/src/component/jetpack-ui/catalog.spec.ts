describe('Jetpack UI catalog', () => {
    it('registers the complete public component catalog', async () => {
        const register = Shopware.Component.register as jest.Mock;
        register.mockClear();

        await jest.isolateModulesAsync(async () => {
            await import('./index');
        });

        const calls = register.mock.calls as Array<[string, unknown]>;
        const names = calls.map(([name]) => name);

        expect(names).toHaveLength(43);
        expect(new Set(names).size).toBe(names.length);
        expect(names).toEqual(
            expect.arrayContaining([
                'jetpack-button',
                'jetpack-icon',
                'jetpack-loader',
                'jetpack-link',
                'jetpack-text-field',
                'jetpack-number-field',
                'jetpack-password-field',
                'jetpack-email-field',
                'jetpack-url-field',
                'jetpack-textarea',
                'jetpack-switch',
                'jetpack-checkbox',
                'jetpack-select',
                'jetpack-search-select',
                'jetpack-multi-select',
                'jetpack-entity-select',
                'jetpack-entity-multi-select',
                'jetpack-inheritance-control',
                'jetpack-datepicker',
                'jetpack-color-picker',
                'jetpack-card',
                'jetpack-grid',
                'jetpack-stack',
                'jetpack-tabs',
                'jetpack-empty-state',
                'jetpack-banner',
                'jetpack-badge',
                'jetpack-progress',
                'jetpack-skeleton',
                'jetpack-pagination',
                'jetpack-modal',
                'jetpack-tooltip',
                'jetpack-popover',
                'jetpack-action-menu',
                'jetpack-context-menu',
                'jetpack-data-table',
                'jetpack-entity-table',
                'jetpack-rich-text-editor',
                'jetpack-chart',
                'jetpack-avatar',
                'jetpack-media-preview',
                'jetpack-media-upload',
            ]),
        );
    });
});
