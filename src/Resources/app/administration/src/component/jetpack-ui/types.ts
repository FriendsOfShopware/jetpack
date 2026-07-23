export type JetpackComponentSize = 'small' | 'default' | 'large';

export type JetpackButtonVariant = 'primary' | 'secondary' | 'tertiary' | 'critical';

export type JetpackOptionValue = string | number | boolean | null;

export type JetpackOption = {
    value: JetpackOptionValue;
    label: string;
    disabled?: boolean;
    description?: string;
};

export type JetpackTabItem = {
    name: string;
    label: string;
    disabled?: boolean;
    error?: boolean;
    badge?: string;
};

export type JetpackTableColumn = {
    property: string;
    label: string;
    sortable?: boolean;
    align?: 'start' | 'center' | 'end';
    width?: string;
};

export type JetpackMenuItem = {
    id: string;
    label: string;
    disabled?: boolean;
    critical?: boolean;
    icon?: string;
};

export type JetpackChartPoint = {
    label: string;
    value: number;
};

export type JetpackIconPath = {
    d: string;
    fill?: boolean;
};
