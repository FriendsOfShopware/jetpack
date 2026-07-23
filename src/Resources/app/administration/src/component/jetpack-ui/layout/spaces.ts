import template from './space.html.twig';

function registerSpace(name: string, mode: 'grid' | 'stack') {
    return Shopware.Component.register(name, {
        template,
        inheritAttrs: false,

        props: {
            tag: { type: String, required: false, default: 'div' },
            columns: { type: Number, required: false, default: 2 },
            gap: { type: String, required: false, default: 'default' },
            direction: { type: String, required: false, default: 'vertical' },
            align: { type: String, required: false, default: 'stretch' },
        },

        computed: {
            classes(): Array<string> {
                return [
                    `jetpack-${mode}`,
                    `jetpack-${mode}--gap-${this.gap}`,
                    `jetpack-${mode}--align-${this.align}`,
                    mode === 'grid'
                        ? `jetpack-grid--columns-${Math.min(4, Math.max(1, this.columns))}`
                        : `jetpack-stack--${this.direction}`,
                ];
            },
        },
    });
}

export const JetpackGrid = registerSpace('jetpack-grid', 'grid');
export const JetpackStack = registerSpace('jetpack-stack', 'stack');
