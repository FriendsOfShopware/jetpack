import template from './template.html.twig';
import type { PropType } from 'vue';
import type { CmsElementDefinition, CmsElementField, CmsElementSelectOption } from '../../types';
import type { CmsElementData, CmsRuntimeElement } from '../types';

type FieldProps = Record<string, unknown>;

type EntityRecord = Record<string, unknown> & { id: string };

type EntityRepository = {
    get: (
        id: string,
        context: unknown,
        criteria?: InstanceType<typeof Shopware.Data.Criteria>,
    ) => Promise<EntityRecord | null>;
};

type RepositoryFactory = {
    create: (entity: string) => EntityRepository;
};

export default Shopware.Component.wrapComponentConfig({
    template,

    emits: ['element-update'],
    inject: ['repositoryFactory'],

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
    },

    methods: {
        componentFor(field: CmsElementField): string {
            return {
                text: 'jetpack-text-field',
                textarea: 'jetpack-textarea',
                number: 'jetpack-number-field',
                switch: 'jetpack-switch',
                select: 'jetpack-select',
                'entity-select': 'jetpack-entity-select',
            }[field.type];
        },

        translateOptions(options: CmsElementSelectOption[]): Array<CmsElementSelectOption & { label: string }> {
            return options.map((option) => ({
                ...option,
                label: String(this.$t(option.labelSnippet)),
            }));
        },

        fieldProps(field: CmsElementField, isInherited: boolean): FieldProps {
            const common: FieldProps = {
                helpText: field.helpTextSnippet ? this.$t(field.helpTextSnippet) : '',
                placeholder: field.placeholderSnippet ? this.$t(field.placeholderSnippet) : '',
                required: field.required ?? false,
                disabled: isInherited || (field.disabled ?? false),
            };

            if (field.type === 'number') {
                return {
                    ...common,
                    min: field.min,
                    max: field.max,
                    step: field.step,
                };
            }

            if (field.type === 'select') {
                return {
                    ...common,
                    options: this.translateOptions(field.options),
                };
            }

            if (field.type === 'entity-select') {
                const criteria = new Shopware.Data.Criteria(1, 25);
                field.criteria?.({ criteria });

                return {
                    ...common,
                    entity: field.entity,
                    labelProperty: field.labelProperty ?? 'name',
                    criteria,
                };
            }

            return common;
        },

        async updateField(field: CmsElementField, value: string | number | boolean | null): Promise<void> {
            this.runtimeElement.config[field.name].value = value;
            if (field.type !== 'entity-select') {
                this.$emit('element-update', this.element);

                return;
            }

            this.runtimeElement.data = {
                ...this.runtimeElement.data,
                [field.name]: null,
            };
            this.$emit('element-update', this.element);

            if (typeof value !== 'string' || !value) {
                return;
            }

            const criteria = new Shopware.Data.Criteria(1, 25);
            field.criteria?.({ criteria });

            try {
                const entity = await (this.repositoryFactory as RepositoryFactory)
                    .create(field.entity)
                    .get(value, Shopware.Context.api, criteria);
                if (this.runtimeElement.config[field.name].value !== value) {
                    return;
                }

                if (this.runtimeElement.data) {
                    this.runtimeElement.data[field.name] = entity;
                }
                this.$emit('element-update', this.element);
            } catch {
                // The selected id remains valid. Shopware's CMS resolver retries enrichment.
            }
        },
    },
});
