import { installFroshJetpackGlobal } from './install-global';

describe('installFroshJetpackGlobal', () => {
    it('exposes the CRUD and CMS registries through one frozen runtime', () => {
        const runtime = installFroshJetpackGlobal();

        expect(runtime.apiVersion).toBe(1);
        expect(runtime.runtimeVersion).toBe('0.3.0');
        expect(typeof runtime.Admin.Crud.register).toBe('function');
        expect(typeof runtime.Admin.Cms.register).toBe('function');
        expect(Object.isFrozen(runtime)).toBe(true);
        expect(Object.isFrozen(runtime.Admin)).toBe(true);
        expect(globalThis.FroshJetpack).toBe(runtime);
    });
});
