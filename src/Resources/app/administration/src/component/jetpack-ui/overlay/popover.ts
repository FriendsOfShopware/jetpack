import template from './popover.html.twig';
import type { PropType } from 'vue';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        modelValue: { type: Boolean, required: false, default: false },
        position: {
            type: String as PropType<'start' | 'center' | 'end'>,
            required: false,
            default: 'end',
        },
        closeOnOutside: { type: Boolean, required: false, default: true },
    },

    emits: [
        'update:modelValue',
        'open',
        'close',
    ],

    data() {
        return {
            documentClickHandler: null as ((event: MouseEvent) => void) | null,
        };
    },

    mounted() {
        this.documentClickHandler = (event) => this.onDocumentClick(event);
        document.addEventListener('mousedown', this.documentClickHandler);
    },

    beforeUnmount() {
        if (this.documentClickHandler) {
            document.removeEventListener('mousedown', this.documentClickHandler);
        }
    },

    methods: {
        toggle(): void {
            this.commit(!this.modelValue);
        },

        commit(open: boolean): void {
            this.$emit('update:modelValue', open);
            this.$emit(open ? 'open' : 'close');
        },

        onDocumentClick(event: MouseEvent): void {
            if (!this.modelValue || !this.closeOnOutside) {
                return;
            }

            const root = this.$refs.root as HTMLElement;

            if (!root.contains(event.target as Node)) {
                this.commit(false);
            }
        },
    },
});
