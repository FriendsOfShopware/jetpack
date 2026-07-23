import template from './entity-multi-select.html.twig';
import type { PropType } from 'vue';
import type { JetpackOption } from '../types';
import {
    selectedIds,
    syncEntityCollection,
    type EntityRecord,
    type MutableEntityCollection,
} from '../../../administration-crud/association/association';

type CriteriaType = InstanceType<typeof Shopware.Data.Criteria>;

type EntityRepository = {
    search: (criteria: unknown, context: unknown) => Promise<EntityRecord[]>;
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
            type: Array as PropType<MutableEntityCollection>,
            required: true,
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
        placeholder: { type: String, required: false, default: '' },
        emptyText: { type: String, required: false, default: '' },
        loadingText: { type: String, required: false, default: '' },
        searchPlaceholder: { type: String, required: false, default: '' },
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
        selectedValues(): string[] {
            return selectedIds(this.modelValue);
        },

        entityOptions(): JetpackOption[] {
            const merged = new Map<string, EntityRecord>();
            this.modelValue.forEach((entity) => merged.set(entity.id, entity));
            this.entities.forEach((entity) => merged.set(entity.id, entity));

            return Array.from(merged.values(), (entity) => ({
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

    methods: {
        async search(term: string): Promise<void> {
            this.loading = true;

            try {
                const criteria = this.criteria
                    ? Shopware.Data.Criteria.fromCriteria(this.criteria)
                    : new Shopware.Data.Criteria(1, 25);
                criteria.setTerm(term);
                this.entities = await this.repository.search(criteria, this.context ?? Shopware.Context.api);
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

        updateSelection(ids: string[]): void {
            const collection = syncEntityCollection(this.modelValue, ids, this.entities);
            this.$emit('update:modelValue', collection);
            this.$emit('change', collection);
        },
    },
});
