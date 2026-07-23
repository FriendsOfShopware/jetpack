export type Translation = Record<string, string>;

export type ScopeSource = {
    salesChannelId: string | null;
    languageId: string | null;
    default: boolean;
};

export type ValueState = {
    value: unknown;
    overridden: boolean;
    inheritedValue: unknown;
    inheritedSource: ScopeSource;
    address: {
        salesChannelId: string | null;
        languageId: string | null;
    };
};

export type FieldDocument = Record<string, unknown> & {
    type: string;
    scope: string;
    label: Translation;
    position: number;
};

export type SectionDocument = Record<string, unknown> & {
    label: Translation;
    description?: Translation;
    position: number;
    fields: Record<string, FieldDocument>;
};

export type TabDocument = Record<string, unknown> & {
    label: Translation;
    position: number;
    sections: Record<string, SectionDocument>;
};

export type ConfigurationDocument = {
    label?: Translation;
    tabs: Record<string, TabDocument>;
};

export type ConfigurationListItem = {
    name: string;
    class: string;
    label: Translation;
};

export type ConfigurationDetailResponse = {
    bundle: { name: string; class: string };
    definition: ConfigurationDocument;
    values: Record<string, ValueState>;
};

export type ConfigurationOperation = {
    key: string;
    salesChannelId: string | null;
    languageId: string | null;
    value?: unknown;
};

export type ConfigurationWritePayload = {
    writes: ConfigurationOperation[];
    deletes: ConfigurationOperation[];
};
