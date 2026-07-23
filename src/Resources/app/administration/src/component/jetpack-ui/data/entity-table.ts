import template from './entity-table.html.twig';
import type { PropType } from 'vue';
import type { JetpackTableColumn } from '../types';

type EntityRecord = Record<string, unknown> & { id: string };
type CriteriaType = InstanceType<typeof Shopware.Data.Criteria>;

type EntitySearchResult = EntityRecord[] & { total?: number };

type EntityRepository = {
    search: (criteria: unknown, context: unknown) => Promise<EntitySearchResult>;
};

type RepositoryFactory = {
    create: (entity: string) => EntityRepository;
};

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,
    inject: ['repositoryFactory'],

    props: {
        entity: { type: String, required: true },
        columns: {
            type: Array as PropType<JetpackTableColumn[]>,
            required: true,
        },
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
        modelValue: {
            type: Array as PropType<Array<string | number>>,
            required: false,
            default: () => [],
        },
        itemKey: { type: String, required: false, default: 'id' },
        selectable: { type: Boolean, required: false, default: false },
        emptyText: {
            type: String,
            required: false,
            default: 'No entries found',
        },
        limit: { type: Number, required: false, default: 25 },
    },

    emits: [
        'update:modelValue',
        'selection-change',
        'row-click',
        'load',
        'load-error',
    ],

    data() {
        return {
            entities: [] as EntityRecord[],
            loading: false,
            total: 0,
            page: 1,
            sortBy: '',
            sortDirection: 'asc' as 'asc' | 'desc',
        };
    },

    computed: {
        repository(): EntityRepository {
            return (this.repositoryFactory as RepositoryFactory).create(this.entity);
        },
    },

    created() {
        void this.load();
    },

    methods: {
        buildCriteria(): object {
            const criteria = this.criteria
                ? Shopware.Data.Criteria.fromCriteria(this.criteria)
                : new Shopware.Data.Criteria(this.page, this.limit);

            criteria.setPage(this.page);

            criteria.setLimit(this.limit);

            if (this.sortBy) {
                criteria.addSorting(
                    Shopware.Data.Criteria.sort(this.sortBy, this.sortDirection.toUpperCase() as 'ASC' | 'DESC'),
                );
            }

            return criteria;
        },

        async load(): Promise<void> {
            this.loading = true;

            try {
                const result = await this.repository.search(this.buildCriteria(), this.context ?? Shopware.Context.api);

                this.entities = Array.from(result);
                this.total = result.total ?? result.length;
                this.$emit('load', result);
            } catch (error) {
                this.$emit('load-error', error);
            } finally {
                this.loading = false;
            }
        },

        onPage(page: number): void {
            this.page = page;
            void this.load();
        },

        onSort(sort: { property: string; direction: 'asc' | 'desc' }): void {
            this.sortBy = sort.property;
            this.sortDirection = sort.direction;
            void this.load();
        },
    },
});
