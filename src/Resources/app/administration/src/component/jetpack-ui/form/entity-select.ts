import template from './entity-select.html.twig';
import type { PropType } from 'vue';
import type { JetpackOption } from '../types';

type EntityRecord = Record<string, unknown> & { id: string };
type CriteriaType = InstanceType<typeof Shopware.Data.Criteria>;

type EntityRepository = {
    search: (criteria: unknown, context: unknown) => Promise<EntityRecord[]>;
    get: (id: string, context: unknown) => Promise<EntityRecord | null>;
};

type RepositoryFactory = {
    create: (entity: string) => EntityRepository;
};

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,
    inject: ['repositoryFactory'],

    props: {
        modelValue: {
            type: [
                String,
                Number,
                Boolean,
            ],
            required: false,
            default: null,
        },
        entity: { type: String, required: true },
        criteria: {
            type: Object as PropType<CriteriaType | null>,
            required: false,
            default: null,
        },
        context: {
            type: Object as PropType<Record<string, unknown> | null>,
            required: false,
            default: null,
        },
        labelProperty: { type: String, required: false, default: 'name' },
        label: { type: String, required: false, default: '' },
        helpText: { type: String, required: false, default: '' },
        error: { type: String, required: false, default: '' },
        placeholder: {
            type: String,
            required: false,
            default: 'Select an entity',
        },
        emptyText: {
            type: String,
            required: false,
            default: 'No entities found',
        },
        loadingText: {
            type: String,
            required: false,
            default: 'Loading entities…',
        },
        disabled: { type: Boolean, required: false, default: false },
        required: { type: Boolean, required: false, default: false },
    },

    emits: [
        'update:modelValue',
        'change',
        'load-error',
    ],

    data() {
        return {
            entities: [] as EntityRecord[],
            loading: false,
        };
    },

    computed: {
        entityOptions(): JetpackOption[] {
            return this.entities.map((entity) => ({
                value: entity.id,
                label: this.entityLabel(entity),
            }));
        },

        repository(): EntityRepository {
            return (this.repositoryFactory as RepositoryFactory).create(this.entity);
        },
    },

    created() {
        void this.search('');
    },

    watch: {
        modelValue(value: string | number | boolean | null): void {
            if (typeof value === 'string') {
                void this.ensureSelectedEntity(value);
            }
        },
    },

    methods: {
        async search(term: string): Promise<void> {
            this.loading = true;

            try {
                const criteria = this.criteria
                    ? Shopware.Data.Criteria.fromCriteria(this.criteria)
                    : new Shopware.Data.Criteria(1, 25);

                criteria.setTerm(term);

                const entities = await this.repository.search(criteria, this.context ?? Shopware.Context.api);
                this.entities = this.mergeEntities(entities);
                if (typeof this.modelValue === 'string') {
                    await this.ensureSelectedEntity(this.modelValue);
                }
            } catch (error) {
                this.$emit('load-error', error);
            } finally {
                this.loading = false;
            }
        },

        entityLabel(entity: EntityRecord): string {
            const label = entity[this.labelProperty];

            return typeof label === 'string' || typeof label === 'number' ? String(label) : entity.id;
        },

        mergeEntities(entities: EntityRecord[]): EntityRecord[] {
            const merged = new Map(
                this.entities.map((entity) => [
                    entity.id,
                    entity,
                ]),
            );
            entities.forEach((entity) => merged.set(entity.id, entity));

            return Array.from(merged.values());
        },

        async ensureSelectedEntity(id: string): Promise<void> {
            if (this.entities.some((entity) => entity.id === id)) {
                return;
            }

            try {
                const entity = await this.repository.get(id, this.context ?? Shopware.Context.api);
                if (entity) {
                    this.entities = this.mergeEntities([entity]);
                }
            } catch (error) {
                this.$emit('load-error', error);
            }
        },
    },
});
