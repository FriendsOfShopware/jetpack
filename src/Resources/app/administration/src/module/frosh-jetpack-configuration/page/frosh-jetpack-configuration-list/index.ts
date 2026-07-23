import template from './frosh-jetpack-configuration-list.html.twig';
import './frosh-jetpack-configuration-list.scss';
import type { ConfigurationListItem, Translation } from '../../../../types';

const { Mixin } = Shopware;

Shopware.Component.register('frosh-jetpack-configuration-list', {
    template,

    inject: ['froshJetpackConfigurationApiService'],

    mixins: [Mixin.getByName('notification')],

    data(): { bundles: ConfigurationListItem[]; isLoading: boolean } {
        return {
            bundles: [],
            isLoading: false,
        };
    },

    created() {
        void this.load();
    },

    methods: {
        async load(): Promise<void> {
            this.isLoading = true;

            try {
                this.bundles = await this.froshJetpackConfigurationApiService.list();
            } catch {
                this.createNotificationError({
                    message: this.$t('frosh-jetpack-configuration.detail.loadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        translate(translations: Translation): string {
            const locale = (Shopware.Context.app.config as { locale?: string }).locale ?? 'en-GB';

            return translations[locale] ?? translations['en-GB'] ?? Object.values(translations)[0] ?? '';
        },
    },
});
