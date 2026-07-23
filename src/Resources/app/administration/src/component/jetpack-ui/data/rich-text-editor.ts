import template from './rich-text-editor.html.twig';

type EditorAction = {
    command: string;
    label: string;
    shortLabel: string;
    value?: string;
};

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        modelValue: { type: String, required: false, default: '' },
        id: { type: String, required: false, default: null },
        label: { type: String, required: false, default: '' },
        helpText: { type: String, required: false, default: '' },
        error: { type: String, required: false, default: '' },
        disabled: { type: Boolean, required: false, default: false },
        required: { type: Boolean, required: false, default: false },
    },

    emits: [
        'update:modelValue',
        'change',
        'blur',
        'focus',
    ],

    data() {
        return {
            actions: [
                { command: 'bold', label: 'Bold', shortLabel: 'B' },
                { command: 'italic', label: 'Italic', shortLabel: 'I' },
                { command: 'underline', label: 'Underline', shortLabel: 'U' },
                {
                    command: 'insertUnorderedList',
                    label: 'Bulleted list',
                    shortLabel: '•',
                },
                {
                    command: 'insertOrderedList',
                    label: 'Numbered list',
                    shortLabel: '1.',
                },
            ] as EditorAction[],
        };
    },

    watch: {
        modelValue(value: string): void {
            const editor = this.$refs.editor as HTMLElement | undefined;

            if (editor && editor.innerHTML !== value) {
                editor.innerHTML = value;
            }
        },
    },

    mounted() {
        (this.$refs.editor as HTMLElement).innerHTML = this.modelValue;
    },

    methods: {
        execute(command: string, value?: string): void {
            if (this.disabled) {
                return;
            }

            document.execCommand(command, false, value);
            (this.$refs.editor as HTMLElement).focus();
            this.onInput();
        },

        onInput(): void {
            const value = (this.$refs.editor as HTMLElement).innerHTML;

            this.$emit('update:modelValue', value);
            this.$emit('change', value);
        },
    },
});
