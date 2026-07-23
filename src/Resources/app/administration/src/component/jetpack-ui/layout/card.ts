import template from './card.html.twig';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        title: { type: String, required: false, default: '' },
        subtitle: { type: String, required: false, default: '' },
        compact: { type: Boolean, required: false, default: false },
    },
});
