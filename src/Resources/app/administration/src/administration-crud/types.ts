import type { CmsElementApi } from '../cms-element/types';

export type CrudEntity = Record<string, unknown> & { id: string };

export type CrudProperty<TEntity extends CrudEntity> =
    | Extract<keyof TEntity, string>
    | `${Extract<keyof TEntity, string>}.${string}`;

export type CrudCriteria = InstanceType<typeof Shopware.Data.Criteria>;

export type CrudApiContext = Record<string, unknown> & {
    languageId?: string | null;
    systemLanguageId?: string;
};

export type CrudRouter = {
    push: (target: unknown) => Promise<unknown> | void;
    replace: (target: unknown) => Promise<unknown> | void;
    back: () => void;
};

export type CrudRole = 'viewer' | 'creator' | 'editor' | 'deleter';
export type CrudActionVariant = 'primary' | 'secondary' | 'tertiary' | 'critical';
export type CrudBadgeVariant = 'neutral' | 'info' | 'positive' | 'warning' | 'critical';

export type CrudSort = {
    property: string;
    direction: 'ASC' | 'DESC';
    naturalSorting?: boolean;
};

export type CrudCriteriaContext = {
    criteria: CrudCriteria;
};

export type CrudColumn<TEntity extends CrudEntity = CrudEntity> = {
    id: string;
    property: CrudProperty<TEntity>;
    labelSnippet?: string;
    type?: 'text' | 'number' | 'currency' | 'boolean' | 'date' | 'badge' | 'custom';
    sortable?: boolean;
    align?: 'start' | 'center' | 'end';
    linkToDetail?: boolean;
    association?: string;
    associationEntity?: string;
    currency?: string;
    badges?: Record<
        string,
        {
            labelSnippet?: string;
            variant?: CrudBadgeVariant;
        }
    >;
    component?: string;
    props?: Record<string, unknown>;
};

export type CrudSelectOption = {
    value: string | number | boolean | null;
    labelSnippet: string;
    disabled?: boolean;
};

type CrudFieldBase<TEntity extends CrudEntity> = {
    id: string;
    property: CrudProperty<TEntity>;
    labelSnippet?: string;
    helpTextSnippet?: string;
    placeholderSnippet?: string;
    required?: boolean;
    disabled?: boolean;
};

export type CrudScalarField<TEntity extends CrudEntity = CrudEntity> = CrudFieldBase<TEntity> & {
    type:
        | 'text'
        | 'email'
        | 'url'
        | 'password'
        | 'number'
        | 'textarea'
        | 'checkbox'
        | 'switch'
        | 'date'
        | 'color'
        | 'rich-text';
    min?: number;
    max?: number;
    step?: number;
};

export type CrudSelectField<TEntity extends CrudEntity = CrudEntity> = CrudFieldBase<TEntity> & {
    type: 'select' | 'multi-select';
    options: CrudSelectOption[];
};

export type CrudEntitySelectField<TEntity extends CrudEntity = CrudEntity> = CrudFieldBase<TEntity> & {
    type: 'entity-select';
    entity: string;
    labelProperty?: string;
    criteria?: (context: CrudCriteriaContext) => void;
};

export type CrudEntityMultiSelectField<TEntity extends CrudEntity = CrudEntity> = CrudFieldBase<TEntity> & {
    type: 'entity-multi-select';
    entity: string;
    labelProperty?: string;
    criteria?: (context: CrudCriteriaContext) => void;
};

export type CrudCustomField<TEntity extends CrudEntity = CrudEntity> = CrudFieldBase<TEntity> & {
    type: 'custom';
    component: string;
    props?: Record<string, unknown>;
};

export type CrudField<TEntity extends CrudEntity = CrudEntity> =
    | CrudScalarField<TEntity>
    | CrudSelectField<TEntity>
    | CrudEntitySelectField<TEntity>
    | CrudEntityMultiSelectField<TEntity>
    | CrudCustomField<TEntity>;

export type CrudCard<TEntity extends CrudEntity = CrudEntity> = {
    id: string;
    titleSnippet?: string;
    fields: CrudField<TEntity>[];
};

export type CrudActionPermission = {
    requiredRole?: CrudRole;
    privilege?: string;
};

export type CrudActionConfirmation = {
    titleSnippet: string;
    messageSnippet: string;
};

type CrudRuntimeContext<TEntity extends CrudEntity> = {
    definition: ResolvedCrudDefinition<TEntity>;
    repository: CrudRepository<TEntity>;
    apiContext: CrudApiContext;
    router: CrudRouter;
    reload: () => Promise<void>;
};

export type CrudRowActionContext<TEntity extends CrudEntity> = CrudRuntimeContext<TEntity> & {
    entity: TEntity;
};

export type CrudRowAction<TEntity extends CrudEntity = CrudEntity> = CrudActionPermission & {
    id: string;
    labelSnippet: string;
    variant?: CrudActionVariant;
    handler: (context: CrudRowActionContext<TEntity>) => void | Promise<void>;
};

export type CrudBulkActionContext<TEntity extends CrudEntity> = CrudRuntimeContext<TEntity> & {
    selectedIds: readonly string[];
    entities: readonly TEntity[];
};

export type CrudBulkAction<TEntity extends CrudEntity = CrudEntity> = CrudActionPermission & {
    id: string;
    labelSnippet: string;
    variant?: CrudActionVariant;
    confirmation?: CrudActionConfirmation;
    handler: (context: CrudBulkActionContext<TEntity>) => void | Promise<void>;
};

export type CrudBulkDefinition<TEntity extends CrudEntity = CrudEntity> = {
    delete?: boolean;
    actions?: CrudBulkAction<TEntity>[];
};

export type CrudListingDefinition<TEntity extends CrudEntity = CrudEntity> = {
    search?: boolean;
    pageSize?: number;
    columns: CrudColumn<TEntity>[];
    associations?: string[];
    defaultSort?: CrudSort[];
    criteria?: (context: CrudCriteriaContext) => void;
    allowDelete?: boolean;
    rowActions?: CrudRowAction<TEntity>[];
    bulk?: CrudBulkDefinition<TEntity> | false;
};

export type CrudDetailActionContext<TEntity extends CrudEntity> = CrudRuntimeContext<TEntity> & {
    entity: TEntity;
    mode: 'create' | 'edit';
};

export type CrudDetailAction<TEntity extends CrudEntity = CrudEntity> = CrudActionPermission & {
    id: string;
    labelSnippet: string;
    variant?: CrudActionVariant;
    handler: (context: CrudDetailActionContext<TEntity>) => void | Promise<void>;
};

export type CrudDetailDefinition<TEntity extends CrudEntity = CrudEntity> = {
    labelProperty?: CrudProperty<TEntity>;
    cards: CrudCard<TEntity>[];
    associations?: string[];
    create?: boolean;
    actions?: CrudDetailAction<TEntity>[];
};

export type CrudTranslationDefinition = {
    enabled: true;
    languageLabelProperty?: string;
};

export type CrudModuleDefinition = {
    titleSnippet: string;
    descriptionSnippet?: string;
    icon?: string;
    color?: string;
    navigation?:
        | false
        | {
              parent: string;
              position?: number;
          };
    settings?:
        | false
        | {
              group?:
                  | 'customer'
                  | 'general'
                  | 'localization'
                  | 'commerce'
                  | 'content'
                  | 'automation'
                  | 'system'
                  | 'account'
                  | 'plugins';
              position?: number;
          };
};

export type CrudAclDefinition = {
    key: string;
    category?: string;
    parent?: string | null;
    additional?: Partial<Record<'viewer' | 'creator' | 'editor' | 'deleter', string[]>>;
};

export type CrudRepository<TEntity extends CrudEntity> = {
    create: (context: unknown) => TEntity;
    get: (id: string, context: unknown, criteria?: CrudCriteria) => Promise<TEntity | null>;
    save: (entity: TEntity, context: unknown) => Promise<unknown>;
    delete: (id: string, context: unknown) => Promise<unknown>;
    syncDeleted?: (ids: string[], context: unknown) => Promise<unknown>;
};

type CrudHookBase<TEntity extends CrudEntity> = {
    definition: ResolvedCrudDefinition<TEntity>;
    repository: CrudRepository<TEntity>;
    apiContext: CrudApiContext;
    router: CrudRouter;
    reload: () => Promise<void>;
};

export type CrudLoadContext<TEntity extends CrudEntity> = CrudHookBase<TEntity> & {
    entityId: string | null;
    criteria: CrudCriteria;
};

export type CrudLoadedContext<TEntity extends CrudEntity> = CrudHookBase<TEntity> & {
    entity: TEntity;
    mode: 'create' | 'edit';
};

export type CrudSaveContext<TEntity extends CrudEntity> = CrudHookBase<TEntity> & {
    entity: TEntity;
    mode: 'create' | 'edit';
};

export type CrudHooks<TEntity extends CrudEntity> = {
    beforeLoad?: (context: CrudLoadContext<TEntity>) => void | Promise<void>;
    afterLoad?: (context: CrudLoadedContext<TEntity>) => void | Promise<void>;
    beforeSave?: (context: CrudSaveContext<TEntity>) => void | Promise<void>;
    afterSave?: (context: CrudSaveContext<TEntity>) => void | Promise<void>;
    onError?: (context: { error: unknown; operation: string }) => void;
};

export type CrudDefinition<TEntity extends CrudEntity = CrudEntity> = {
    apiVersion: 1;
    id: string;
    entity: string;
    snippets: string;
    module: CrudModuleDefinition;
    acl: CrudAclDefinition;
    listing: CrudListingDefinition<TEntity> | false;
    detail: CrudDetailDefinition<TEntity> | false;
    translation?: CrudTranslationDefinition | false;
    hooks?: CrudHooks<TEntity>;
};

export type CrudPlacement = {
    before?: string;
    after?: string;
    position?: number;
};

export type CrudExtension<TEntity extends CrudEntity = CrudEntity> = {
    apiVersion: 1;
    id: string;
    listing?: {
        columns?: Array<CrudColumn<TEntity> & CrudPlacement>;
        rowActions?: Array<CrudRowAction<TEntity> & CrudPlacement>;
        bulkActions?: Array<CrudBulkAction<TEntity> & CrudPlacement>;
    };
    detail?: {
        cards?: Array<CrudCard<TEntity> & CrudPlacement>;
        fields?: Array<CrudField<TEntity> & CrudPlacement & { card: string }>;
        actions?: Array<CrudDetailAction<TEntity> & CrudPlacement>;
    };
};

export type ResolvedCrudDefinition<TEntity extends CrudEntity = CrudEntity> = CrudDefinition<TEntity> & {
    moduleName: string;
    routeNames: CrudRouteNames;
};

export type CrudRouteNames = {
    list?: string;
    create?: string;
    detail?: string;
};

export type CrudRegistration = {
    id: string;
    moduleName: string;
    routeNames: Readonly<CrudRouteNames>;
};

export type CrudExtensionRegistration = {
    id: string;
    target: string;
};

export interface CrudApi {
    register<TEntity extends CrudEntity>(definition: CrudDefinition<TEntity>): CrudRegistration;
    extend<TEntity extends CrudEntity>(target: string, extension: CrudExtension<TEntity>): CrudExtensionRegistration;
    get(id: string): Readonly<ResolvedCrudDefinition> | undefined;
    all(): readonly Readonly<ResolvedCrudDefinition>[];
}

export interface FroshJetpackGlobal {
    readonly apiVersion: 1;
    readonly runtimeVersion: string;
    readonly Admin: {
        readonly Crud: CrudApi;
        readonly Cms: CmsElementApi;
    };
}
