import template from './data-table.html.twig';
import type { PropType } from 'vue';
import type { JetpackTableColumn } from '../types';
import { getByPath } from '../utils';

type TableItem = Record<string, unknown>;

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        items: {
            type: Array as PropType<TableItem[]>,
            required: false,
            default: () => [],
        },
        columns: {
            type: Array as PropType<JetpackTableColumn[]>,
            required: true,
        },
        modelValue: {
            type: Array as PropType<Array<string | number>>,
            required: false,
            default: () => [],
        },
        itemKey: { type: String, required: false, default: 'id' },
        selectable: { type: Boolean, required: false, default: false },
        loading: { type: Boolean, required: false, default: false },
        loadingText: { type: String, required: false, default: 'Loading rows' },
        emptyText: {
            type: String,
            required: false,
            default: 'No entries found',
        },
        sortBy: { type: String, required: false, default: '' },
        sortDirection: {
            type: String as PropType<'asc' | 'desc'>,
            required: false,
            default: 'asc',
        },
    },

    emits: [
        'update:modelValue',
        'selection-change',
        'sort-change',
        'row-click',
    ],

    computed: {
        allSelected(): boolean {
            return this.items.length > 0 && this.items.every((item) => this.isSelected(item));
        },
    },

    methods: {
        value(item: TableItem, property: string): unknown {
            return getByPath(item, property);
        },

        key(item: TableItem): string | number {
            return item[this.itemKey] as string | number;
        },

        isSelected(item: TableItem): boolean {
            return this.modelValue.includes(this.key(item));
        },

        toggle(item: TableItem): void {
            const key = this.key(item);
            const next = this.isSelected(item)
                ? this.modelValue.filter((candidate) => candidate !== key)
                : [
                      ...this.modelValue,
                      key,
                  ];

            this.commitSelection(next);
        },

        toggleAll(): void {
            this.commitSelection(this.allSelected ? [] : this.items.map((item) => this.key(item)));
        },

        commitSelection(selection: Array<string | number>): void {
            this.$emit('update:modelValue', selection);
            this.$emit('selection-change', selection);
        },

        sort(property: string): void {
            const direction = this.sortBy === property && this.sortDirection === 'asc' ? 'desc' : 'asc';

            this.$emit('sort-change', { property, direction });
        },
    },
});
