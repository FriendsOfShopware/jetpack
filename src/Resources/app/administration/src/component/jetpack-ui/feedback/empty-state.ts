import template from './empty-state.html.twig';

export default Shopware.Component.wrapComponentConfig({
    template,
    inheritAttrs: false,

    props: {
        title: { type: String, required: true },
        description: { type: String, required: false, default: '' },
        icon: { type: String, required: false, default: 'info' },
    },
});
