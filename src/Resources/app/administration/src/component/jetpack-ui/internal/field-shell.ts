import template from './field-shell.html.twig';
import { createJetpackId } from '../utils';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        id: {
            type: String,
            required: false,
            default: null,
        },
        label: {
            type: String,
            required: false,
            default: '',
        },
        helpText: {
            type: String,
            required: false,
            default: '',
        },
        error: {
            type: String,
            required: false,
            default: '',
        },
        required: {
            type: Boolean,
            required: false,
            default: false,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            generatedId: createJetpackId('field'),
        };
    },

    computed: {
        controlId(): string {
            return this.id || this.generatedId;
        },

        helpId(): string | undefined {
            return this.helpText ? `${this.controlId}-help` : undefined;
        },

        errorId(): string | undefined {
            return this.error ? `${this.controlId}-error` : undefined;
        },

        describedBy(): string | undefined {
            return (
                [
                    this.helpId,
                    this.errorId,
                ]
                    .filter(Boolean)
                    .join(' ') || undefined
            );
        },
    },
});
