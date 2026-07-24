export type CmsElementValue = string | number | boolean | null;

export type CmsElementSelectOption = {
    value: CmsElementValue;
    labelSnippet: string;
    disabled?: boolean;
};

export type CmsElementCriteria = InstanceType<typeof Shopware.Data.Criteria>;

export type CmsElementCriteriaContext = {
    criteria: CmsElementCriteria;
};

type CmsElementFieldBase = {
    name: string;
    labelSnippet: string;
    helpTextSnippet?: string;
    placeholderSnippet?: string;
    required?: boolean;
    disabled?: boolean;
};

export type CmsElementTextField = CmsElementFieldBase & {
    type: 'text' | 'textarea';
    defaultValue?: string;
};

export type CmsElementNumberField = CmsElementFieldBase & {
    type: 'number';
    defaultValue?: number | null;
    min?: number;
    max?: number;
    step?: number;
};

export type CmsElementSwitchField = CmsElementFieldBase & {
    type: 'switch';
    defaultValue?: boolean;
};

export type CmsElementSelectField = CmsElementFieldBase & {
    type: 'select';
    defaultValue?: CmsElementValue;
    options: CmsElementSelectOption[];
};

export type CmsElementEntitySelectField = CmsElementFieldBase & {
    type: 'entity-select';
    entity: string;
    defaultValue?: string | null;
    labelProperty?: string;
    criteria?: (context: CmsElementCriteriaContext) => void;
};

export type CmsElementField =
    | CmsElementTextField
    | CmsElementNumberField
    | CmsElementSwitchField
    | CmsElementSelectField
    | CmsElementEntitySelectField;

export type CmsElementDefinition = {
    apiVersion: 1;
    name: string;
    labelSnippet: string;
    fields: CmsElementField[];
    allowedPageTypes?: string[];
    hidden?: boolean;
    removable?: boolean;
};

export type CmsElementRegistration = {
    name: string;
};

export interface CmsElementApi {
    register(definition: CmsElementDefinition): CmsElementRegistration;
    get(name: string): Readonly<CmsElementDefinition> | undefined;
    all(): readonly Readonly<CmsElementDefinition>[];
}
