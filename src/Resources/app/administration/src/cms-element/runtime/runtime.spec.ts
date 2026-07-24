describe('CMS element runtime', () => {
    it('registers one shared component set for every declarative element', async () => {
        const registerComponent = Shopware.Component.register as jest.Mock;
        const registerModule = Shopware.Module.register as jest.Mock;
        registerComponent.mockClear();
        registerModule.mockClear();

        await jest.isolateModulesAsync(async () => {
            await import('./index');
        });

        const componentNames = (registerComponent.mock.calls as Array<[string, unknown]>).map(([name]) => name);
        expect(componentNames).toEqual([
            'frosh-jetpack-cms-element',
            'frosh-jetpack-cms-element-config',
            'frosh-jetpack-cms-element-preview',
        ]);
        type RuntimeModule = {
            snippets: Record<string, unknown>;
        };
        const moduleCall = registerModule.mock.calls[0] as unknown as [string, RuntimeModule];
        expect(moduleCall[0]).toBe('frosh-jetpack-cms-runtime');
        expect(moduleCall[1].snippets['de-DE']).toBeDefined();
        expect(moduleCall[1].snippets['en-GB']).toBeDefined();
    });
});
