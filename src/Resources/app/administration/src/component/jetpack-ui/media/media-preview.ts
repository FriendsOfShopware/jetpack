import template from './media-preview.html.twig';
import type { PropType } from 'vue';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        src: { type: String, required: true },
        mediaType: {
            type: String as PropType<'image' | 'video' | 'file'>,
            required: false,
            default: 'image',
        },
        fileName: { type: String, required: false, default: '' },
        alt: { type: String, required: false, default: '' },
        caption: { type: String, required: false, default: '' },
        removable: { type: Boolean, required: false, default: false },
    },

    emits: ['remove'],
});
