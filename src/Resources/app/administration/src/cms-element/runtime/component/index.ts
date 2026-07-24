import template from './template.html.twig';
import type { PropType } from 'vue';
import type { CmsElementDefinition, CmsElementEntitySelectField, CmsElementField } from '../../types';
import type { CmsElementData, CmsRuntimeElement } from '../types';

function translatedProperty(entity: Record<string, unknown>, property: string): unknown {
    const translated = entity.translated;
    if (translated !== null && typeof translated === 'object') {
        const value = (translated as Record<string, unknown>)[property];
        if (value !== undefined && value !== null && value !== '') {
            return value;
        }
    }

    return entity[property];
}

export default Shopware.Component.wrapComponentConfig({
    template,

    mixins: [
        Shopware.Mixin.getByName('cms-element'),
    ],

    props: {
        elementData: {
            type: Object as PropType<CmsElementData>,
            required: true,
        },
    },

    computed: {
        runtimeElement(): CmsRuntimeElement {
            return this.element as unknown as CmsRuntimeElement;
        },

        definition(): Readonly<CmsElementDefinition> {
            const definition = globalThis.FroshJetpack.Admin.Cms.get(this.runtimeElement.type);
            if (!definition) {
                throw new Error(`[FroshJetpack Admin CMS] ${this.runtimeElement.type}: registration was not found`);
            }

            return definition;
        },
    },

    created() {
        this.initElementConfig();
        this.initElementData(this.runtimeElement.type);
    },

    methods: {
        entityValue(field: CmsElementEntitySelectField): string | number | null {
            const entity = this.runtimeElement.data?.[field.name];
            if (entity === null || typeof entity !== 'object') {
                const value = this.runtimeElement.config[field.name]?.value;

                return typeof value === 'string' || typeof value === 'number' ? value : null;
            }

            const record = entity as Record<string, unknown>;
            const label = translatedProperty(record, field.labelProperty ?? 'name');
            if (typeof label === 'string' || typeof label === 'number') {
                return label;
            }

            return typeof record.id === 'string' || typeof record.id === 'number' ? record.id : null;
        },

        displayValue(field: CmsElementField): string {
            if (field.type === 'entity-select') {
                return String(this.entityValue(field) ?? this.$t('frosh-jetpack-cms.values.notConfigured'));
            }

            const value = this.runtimeElement.config[field.name]?.value;
            if (field.type === 'switch') {
                return String(this.$t(value ? 'frosh-jetpack-cms.values.yes' : 'frosh-jetpack-cms.values.no'));
            }

            if (field.type === 'select') {
                const option = field.options.find((candidate) => candidate.value === value);
                return String(option ? this.$t(option.labelSnippet) : value);
            }

            return String(value ?? this.$t('frosh-jetpack-cms.values.notConfigured'));
        },
    },
});
