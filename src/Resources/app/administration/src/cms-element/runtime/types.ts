import type { CmsElementValue } from '../types';

export type CmsElementData = {
    name: string;
    label?: string;
    defaultConfig?: Record<
        string,
        {
            source: 'static' | 'mapped' | 'default';
            value: CmsElementValue;
        }
    >;
};

export type CmsRuntimeElement = {
    type: string;
    config: Record<
        string,
        {
            source: 'static' | 'mapped' | 'default';
            value: CmsElementValue;
        }
    >;
    data?: Record<string, unknown>;
};
