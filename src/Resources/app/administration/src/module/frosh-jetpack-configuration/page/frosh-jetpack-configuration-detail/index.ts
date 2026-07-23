import template from './frosh-jetpack-configuration-detail.html.twig';
import './frosh-jetpack-configuration-detail.scss';
import { buildOperations } from './scope-state';
import type {
    ConfigurationDetailResponse,
    FieldDocument,
    ScopeSource,
    SectionDocument,
    TabDocument,
    Translation,
    ValueState,
} from '../../../../types';
import type { JetpackOption, JetpackTabItem } from '../../../../component/jetpack-ui/types';

const { Criteria } = Shopware.Data;
const { Mixin } = Shopware;

type ScopeState = {
    response: ConfigurationDetailResponse;
    values: Record<string, ValueState>;
    original: Record<string, ValueState>;
};

type ScopeEntity = {
    id: string;
    name: string | null;
};

type ComponentData = {
    salesChannelId: string | null;
    languageId: string | null;
    salesChannels: ScopeEntity[];
    languages: ScopeEntity[];
    scopeStates: Record<string, ScopeState>;
    activeTab: string | null;
    isLoading: boolean;
};

Shopware.Component.register('frosh-jetpack-configuration-detail', {
    template,

    inject: [
        'repositoryFactory',
        'froshJetpackConfigurationApiService',
        'acl',
    ],

    mixins: [Mixin.getByName('notification')],

    props: {
        bundle: {
            type: String,
            required: true,
        },
    },

    data(): ComponentData {
        return {
            salesChannelId: null,
            languageId: null,
            salesChannels: [],
            languages: [],
            scopeStates: {} as Record<string, ScopeState>,
            activeTab: null,
            isLoading: false,
        };
    },

    computed: {
        scopeKey(): string {
            return `${this.salesChannelId ?? 'global'}|${this.languageId ?? 'global'}`;
        },

        state(): ScopeState | null {
            return this.scopeStates[this.scopeKey] ?? null;
        },

        definition(): ConfigurationDetailResponse['definition'] | null {
            return this.state?.response.definition ?? null;
        },

        tabs(): Array<TabDocument & { key: string }> {
            const tabs = this.definition?.tabs ?? {};

            return Object.entries(tabs)
                .map(
                    ([
                        key,
                        tab,
                    ]) => ({ key, ...tab }),
                )
                .sort((left, right) => Number(left.position) - Number(right.position));
        },

        tabItems(): JetpackTabItem[] {
            return this.tabs.map((tab) => ({
                name: tab.key,
                label: this.translate(tab.label),
            }));
        },

        salesChannelOptions(): JetpackOption[] {
            return [
                {
                    value: null,
                    label: this.$t('frosh-jetpack-configuration.detail.global'),
                },
                ...this.salesChannels.map((salesChannel) => ({
                    value: salesChannel.id,
                    label: salesChannel.name ?? salesChannel.id,
                })),
            ];
        },

        languageOptions(): JetpackOption[] {
            return [
                {
                    value: null,
                    label: this.$t('frosh-jetpack-configuration.detail.languageIndependent'),
                },
                ...this.languages.map((language) => ({
                    value: language.id,
                    label: language.name ?? language.id,
                })),
            ];
        },

        canEdit(): boolean {
            return this.acl.can('frosh_jetpack_configuration:update');
        },

        salesChannelCriteria() {
            return new Criteria(1, 500).addSorting(Criteria.sort('name', 'ASC'));
        },

        languageCriteria() {
            return new Criteria(1, 500).addSorting(Criteria.sort('name', 'ASC'));
        },
    },

    watch: {
        salesChannelId() {
            void this.loadScope();
        },
        languageId() {
            void this.loadScope();
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            await Promise.all([
                this.loadSalesChannels(),
                this.loadLanguages(),
            ]);
            await this.loadScope();
        },

        async loadSalesChannels(): Promise<void> {
            this.salesChannels = (await this.repositoryFactory
                .create('sales_channel')
                .search(this.salesChannelCriteria, Shopware.Context.api)) as unknown as ScopeEntity[];
        },

        async loadLanguages(): Promise<void> {
            this.languages = (await this.repositoryFactory
                .create('language')
                .search(this.languageCriteria, Shopware.Context.api)) as unknown as ScopeEntity[];
        },

        async loadScope(force = false): Promise<void> {
            if (!force && this.scopeStates[this.scopeKey]) {
                return;
            }

            this.isLoading = true;
            const requestedScope = this.scopeKey;

            try {
                const response = await this.froshJetpackConfigurationApiService.detail(
                    this.bundle,
                    this.salesChannelId,
                    this.languageId,
                );
                const values = Shopware.Utils.object.deepCopyObject(response.values);

                this.scopeStates[requestedScope] = {
                    response,
                    values,
                    original: Shopware.Utils.object.deepCopyObject(values),
                };

                if (!this.activeTab) {
                    this.activeTab = Object.keys(response.definition.tabs)[0] ?? null;
                }
            } catch {
                this.createNotificationError({
                    message: this.$t('frosh-jetpack-configuration.detail.loadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        sections(tab: TabDocument): Array<SectionDocument & { key: string }> {
            return Object.entries(tab.sections)
                .map(
                    ([
                        key,
                        section,
                    ]) => ({ key, ...section }),
                )
                .sort((left, right) => Number(left.position) - Number(right.position));
        },

        fields(section: SectionDocument): Array<FieldDocument & { key: string }> {
            return Object.entries(section.fields)
                .map(
                    ([
                        key,
                        field,
                    ]) => ({ key, ...field }),
                )
                .sort((left, right) => Number(left.position) - Number(right.position));
        },

        translate(translations: Translation): string {
            const locale = (Shopware.Context.app.config as { locale?: string }).locale ?? 'en-GB';

            return translations[locale] ?? translations['en-GB'] ?? Object.values(translations)[0] ?? '';
        },

        setOverridden(key: string, overridden: boolean): void {
            const entry = this.state?.values[key];
            if (!entry) {
                return;
            }

            entry.overridden = overridden;
            if (!overridden) {
                entry.value = Array.isArray(entry.inheritedValue)
                    ? (entry.inheritedValue as unknown[]).slice()
                    : entry.inheritedValue;
            }
        },

        sourceLabel(source: ScopeSource): string {
            if (source.default) {
                return this.$t('frosh-jetpack-configuration.detail.defaultSource');
            }

            const salesChannel = this.salesChannels.find((item) => item.id === source.salesChannelId)?.name;
            const language = this.languages.find((item) => item.id === source.languageId)?.name;

            return (
                [
                    salesChannel,
                    language,
                ]
                    .filter(Boolean)
                    .join(' / ') || this.$t('frosh-jetpack-configuration.detail.globalSource')
            );
        },

        async onSave(): Promise<void> {
            if (!this.state) {
                return;
            }

            const { writes, deletes } = buildOperations(
                this.state.values,
                this.state.original,
                Shopware.Utils.types.isEqual,
            );

            if (writes.length === 0 && deletes.length === 0) {
                return;
            }

            this.isLoading = true;
            try {
                await this.froshJetpackConfigurationApiService.save(this.bundle, { writes, deletes });
                delete this.scopeStates[this.scopeKey];
                await this.loadScope(true);
                this.createNotificationSuccess({
                    message: this.$t('frosh-jetpack-configuration.detail.saveSuccess'),
                });
            } catch {
                this.createNotificationError({
                    message: this.$t('frosh-jetpack-configuration.detail.saveError'),
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
