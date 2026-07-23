import type { ConfigurationDetailResponse, ConfigurationListItem, ConfigurationWritePayload } from '../types';

const { ApiService } = Shopware.Classes;

type ApiServiceArguments = ConstructorParameters<typeof ApiService>;

export default class FroshJetpackConfigurationApiService extends ApiService {
    constructor(httpClient: ApiServiceArguments[0], loginService: ApiServiceArguments[1]) {
        super(httpClient, loginService, 'frosh-jetpack/configuration');
        this.name = 'froshJetpackConfigurationApiService';
    }

    list(): Promise<ConfigurationListItem[]> {
        return this.httpClient
            .get('/_action/frosh-jetpack/configuration', {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response) as ConfigurationListItem[]);
    }

    detail(bundle: string, salesChannelId: string | null, languageId: string | null): Promise<ConfigurationDetailResponse> {
        const additionalHeaders = languageId ? { 'sw-language-id': languageId } : {};

        return this.httpClient
            .get(`/_action/frosh-jetpack/configuration/${encodeURIComponent(bundle)}`, {
                params: { salesChannelId, languageId },
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response) => ApiService.handleResponse(response) as ConfigurationDetailResponse);
    }

    save(bundle: string, payload: ConfigurationWritePayload): Promise<unknown> {
        return this.httpClient
            .patch(`/_action/frosh-jetpack/configuration/${encodeURIComponent(bundle)}`, payload, {
                headers: this.getBasicHeaders(),
            })
            .then((response): unknown => ApiService.handleResponse(response) as unknown);
    }
}

Shopware.Application.addServiceProvider('froshJetpackConfigurationApiService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');

    return new FroshJetpackConfigurationApiService(initContainer.httpClient, container.loginService);
});
