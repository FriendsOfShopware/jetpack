import type { CmsElementDefinition, CmsElementField, CmsElementValue } from './types';

type CmsSlotConfig = {
    source: 'static';
    value: CmsElementValue;
    required?: boolean;
    entity?: {
        name: string;
        criteria: InstanceType<typeof Shopware.Data.Criteria>;
    };
};

type CmsService = {
    getCollectFunction: () => (slot: unknown) => Record<string, unknown>;
    registerCmsElement: (config: {
        name: string;
        label: string;
        component: string;
        configComponent: string;
        previewComponent: string;
        defaultConfig: Record<string, CmsSlotConfig>;
        defaultData: Record<string, unknown>;
        allowedPageTypes?: string[];
        hidden?: boolean;
        removable?: boolean;
        collect: (slot: unknown) => Record<string, unknown>;
    }) => boolean;
};

function defaultValue(field: CmsElementField): CmsElementValue {
    if (field.defaultValue !== undefined) {
        return field.defaultValue;
    }

    if (field.type === 'switch') {
        return false;
    }

    if (field.type === 'text' || field.type === 'textarea') {
        return '';
    }

    return null;
}

function createFieldConfig(field: CmsElementField): CmsSlotConfig {
    const config: CmsSlotConfig = {
        source: 'static',
        value: defaultValue(field),
        ...(field.required ? { required: true } : {}),
    };

    if (field.type === 'entity-select') {
        const criteria = new Shopware.Data.Criteria(1, 25);
        field.criteria?.({ criteria });
        config.entity = {
            name: field.entity,
            criteria,
        };
    }

    return config;
}

export function registerCmsElement(definition: Readonly<CmsElementDefinition>): void {
    const cmsService = Shopware.Service('cmsService') as CmsService;
    const defaultConfig = Object.fromEntries(
        definition.fields.map((field) => [
            field.name,
            createFieldConfig(field),
        ]),
    );
    const defaultData = Object.fromEntries(
        definition.fields
            .filter((field) => field.type === 'entity-select')
            .map((field) => [
                field.name,
                null,
            ]),
    );
    const registered = cmsService.registerCmsElement({
        name: definition.name,
        label: definition.labelSnippet,
        component: 'frosh-jetpack-cms-element',
        configComponent: 'frosh-jetpack-cms-element-config',
        previewComponent: 'frosh-jetpack-cms-element-preview',
        defaultConfig,
        defaultData,
        collect: cmsService.getCollectFunction(),
        ...(definition.allowedPageTypes ? { allowedPageTypes: [...definition.allowedPageTypes] } : {}),
        ...(definition.hidden !== undefined ? { hidden: definition.hidden } : {}),
        ...(definition.removable !== undefined ? { removable: definition.removable } : {}),
    });

    if (!registered) {
        throw new Error(`[FroshJetpack Admin CMS] ${definition.name}: Shopware rejected the registration`);
    }
}
