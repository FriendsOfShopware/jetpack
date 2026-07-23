import type FroshJetpackConfigurationApiService from './service/frosh-jetpack-configuration.api.service';
import type { FroshJetpackGlobal } from './administration-crud/types';

declare global {
    var FroshJetpack: FroshJetpackGlobal;

    interface ServiceContainer {
        froshJetpackConfigurationApiService: FroshJetpackConfigurationApiService;
    }
}

export {};
