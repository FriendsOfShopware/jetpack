import template from './media-upload.html.twig';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        id: { type: String, required: false, default: null },
        label: { type: String, required: false, default: '' },
        helpText: { type: String, required: false, default: '' },
        error: { type: String, required: false, default: '' },
        title: {
            type: String,
            required: false,
            default: 'Choose a file or drop it here',
        },
        description: { type: String, required: false, default: '' },
        accept: { type: String, required: false, default: '' },
        multiple: { type: Boolean, required: false, default: false },
        disabled: { type: Boolean, required: false, default: false },
        required: { type: Boolean, required: false, default: false },
    },

    emits: [
        'update:modelValue',
        'change',
        'reject',
    ],

    data() {
        return { dragging: false };
    },

    methods: {
        onChange(event: Event): void {
            const files = Array.from((event.target as HTMLInputElement).files ?? []);

            this.commit(files);
        },

        onDrop(event: DragEvent): void {
            this.dragging = false;

            if (this.disabled) {
                return;
            }

            this.commit(Array.from(event.dataTransfer?.files ?? []));
        },

        commit(files: File[]): void {
            if (files.length === 0) {
                return;
            }

            const value = this.multiple ? files : files[0];

            this.$emit('update:modelValue', value);
            this.$emit('change', value);
        },
    },
});
