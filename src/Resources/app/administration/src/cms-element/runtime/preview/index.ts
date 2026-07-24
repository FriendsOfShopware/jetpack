import template from './template.html.twig';
import type { PropType } from 'vue';
import type { CmsElementDefinition } from '../../types';
import type { CmsElementData } from '../types';

export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        elementData: {
            type: Object as PropType<CmsElementData>,
            required: true,
        },
    },

    computed: {
        definition(): Readonly<CmsElementDefinition> {
            const definition = globalThis.FroshJetpack.Admin.Cms.get(this.elementData.name);
            if (!definition) {
                throw new Error(`[FroshJetpack Admin CMS] ${this.elementData.name}: registration was not found`);
            }

            return definition;
        },
    },
});
