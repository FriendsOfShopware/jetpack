import template from './modal.html.twig';
import type { PropType } from 'vue';
import { createJetpackId } from '../utils';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        modelValue: { type: Boolean, required: false, default: false },
        title: { type: String, required: true },
        size: {
            type: String as PropType<'small' | 'default' | 'large'>,
            required: false,
            default: 'default',
        },
        closeOnBackdrop: { type: Boolean, required: false, default: true },
        closeOnEscape: { type: Boolean, required: false, default: true },
    },

    emits: [
        'update:modelValue',
        'close',
    ],

    data() {
        return {
            titleId: createJetpackId('modal-title'),
            keydownHandler: null as ((event: KeyboardEvent) => void) | null,
        };
    },

    watch: {
        modelValue(open: boolean): void {
            if (open) {
                void this.$nextTick(() => (this.$refs.dialog as HTMLElement | undefined)?.focus());
            }
        },
    },

    mounted() {
        this.keydownHandler = (event) => this.onKeydown(event);
        document.addEventListener('keydown', this.keydownHandler);
    },

    beforeUnmount() {
        if (this.keydownHandler) {
            document.removeEventListener('keydown', this.keydownHandler);
        }
    },

    methods: {
        close(): void {
            this.$emit('update:modelValue', false);
            this.$emit('close');
        },

        onBackdrop(): void {
            if (this.closeOnBackdrop) {
                this.close();
            }
        },

        onKeydown(event: KeyboardEvent): void {
            if (this.modelValue && this.closeOnEscape && event.key === 'Escape') {
                this.close();
            }
        },
    },
});
