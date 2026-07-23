import template from './pagination.html.twig';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        modelValue: { type: Number, required: false, default: 1 },
        total: { type: Number, required: true },
        limit: { type: Number, required: false, default: 25 },
        ariaLabel: { type: String, required: false, default: 'Pagination' },
    },

    emits: [
        'update:modelValue',
        'change',
    ],

    computed: {
        pageCount(): number {
            return Math.max(1, Math.ceil(this.total / this.limit));
        },

        visiblePages(): Array<number | 'ellipsis'> {
            if (this.pageCount <= 7) {
                return Array.from({ length: this.pageCount }, (_, index) => index + 1);
            }

            const pages = new Set([
                1,
                this.pageCount,
                this.modelValue - 1,
                this.modelValue,
                this.modelValue + 1,
            ]);
            const sorted = [...pages].filter((page) => page > 0 && page <= this.pageCount).sort((a, b) => a - b);
            const result: Array<number | 'ellipsis'> = [];

            sorted.forEach((page, index) => {
                if (index > 0 && page - sorted[index - 1] > 1) {
                    result.push('ellipsis');
                }

                result.push(page);
            });

            return result;
        },
    },

    methods: {
        select(page: number): void {
            if (page < 1 || page > this.pageCount || page === this.modelValue) {
                return;
            }

            this.$emit('update:modelValue', page);
            this.$emit('change', page);
        },
    },
});
