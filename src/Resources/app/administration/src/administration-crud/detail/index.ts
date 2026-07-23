import template from './detail.html.twig';
import { applyAssociations, collectDetailAssociations } from '../association/association';
import type {
    CrudApiContext,
    CrudCriteria,
    CrudDetailAction,
    CrudDetailDefinition,
    CrudEntity,
    CrudField,
    CrudRepository,
    CrudRouter,
    ResolvedCrudDefinition,
} from '../types';
import {
    createApiContext,
    getPropertyError,
    initialLanguageId,
    propertyErrorMessage,
    removePropertyError,
    systemLanguageId,
} from '../compatibility/host';

type RepositoryFactory = {
    create: (entity: string) => CrudRepository<CrudEntity>;
};

type AclService = {
    can: (privilege: string) => boolean;
};

function getByPath(entity: CrudEntity, path: string): unknown {
    return path.split('.').reduce<unknown>((value, segment) => {
        if (value === null || typeof value !== 'object') {
            return undefined;
        }

        return (value as Record<string, unknown>)[segment];
    }, entity);
}

function setByPath(entity: CrudEntity, path: string, value: unknown): void {
    const segments = path.split('.');
    const property = segments.pop();
    if (!property) {
        return;
    }

    let target: Record<string, unknown> = entity;
    segments.forEach((segment) => {
        const child = target[segment];
        if (child === null || typeof child !== 'object') {
            target[segment] = {};
        }
        target = target[segment] as Record<string, unknown>;
    });
    target[property] = value;
}

export default Shopware.Component.wrapComponentConfig({
    template,
    inject: [
        'repositoryFactory',
        'acl',
    ],

    props: {
        crudId: { type: String, required: true },
        entityId: { type: String, required: false, default: null },
    },

    data() {
        return {
            entity: null as CrudEntity | null,
            loading: false,
            saving: false,
            loadError: false,
            saveError: false,
            actionError: false,
            runningAction: null as string | null,
            languageId: initialLanguageId(),
        };
    },

    computed: {
        definition(): Readonly<ResolvedCrudDefinition> {
            const definition = globalThis.FroshJetpack.Admin.Crud.get(this.crudId);
            if (!definition) {
                throw new Error(`[FroshJetpack Admin CRUD] ${this.crudId}: registration was not found`);
            }

            return definition;
        },

        detail(): CrudDetailDefinition {
            if (this.definition.detail === false) {
                throw new Error(`[FroshJetpack Admin CRUD] ${this.crudId}: detail is disabled`);
            }

            return this.definition.detail;
        },

        repository(): CrudRepository<CrudEntity> {
            return (this.repositoryFactory as RepositoryFactory).create(this.definition.entity);
        },

        apiContext(): CrudApiContext {
            return createApiContext(this.languageId);
        },

        translationEnabled(): boolean {
            return this.definition.translation !== false && this.definition.translation?.enabled === true;
        },

        mode(): 'create' | 'edit' {
            return this.entityId ? 'edit' : 'create';
        },

        canSave(): boolean {
            const role = this.mode === 'create' ? 'creator' : 'editor';

            return (this.acl as AclService).can(`${this.definition.acl.key}.${role}`);
        },

        entityLabel(): string {
            if (!this.entity) {
                return '';
            }

            const value = getByPath(this.entity, String(this.detail.labelProperty ?? 'id'));

            return typeof value === 'string' || typeof value === 'number' ? String(value) : this.entity.id;
        },
    },

    watch: {
        entityId(): void {
            void this.load();
        },
    },

    created() {
        if (!this.entityId) {
            this.languageId = systemLanguageId();
        }

        void this.load();
    },

    methods: {
        detailCriteria(): CrudCriteria {
            const criteria = new Shopware.Data.Criteria(1, 1);
            applyAssociations(criteria, collectDetailAssociations(this.detail));

            return criteria;
        },

        async load(): Promise<void> {
            this.loading = true;
            this.loadError = false;
            const criteria = this.detailCriteria();

            try {
                await this.definition.hooks?.beforeLoad?.({
                    definition: this.definition,
                    repository: this.repository,
                    entityId: this.entityId,
                    criteria,
                    apiContext: this.apiContext,
                    router: this.$router as unknown as CrudRouter,
                    reload: () => this.load(),
                });

                const entity = this.entityId
                    ? await this.repository.get(this.entityId, this.apiContext, criteria)
                    : this.repository.create(this.apiContext);

                if (!entity) {
                    throw new Error(`[FroshJetpack Admin CRUD] ${this.crudId}: entity "${this.entityId}" was not found`);
                }

                this.entity = entity;
                await this.definition.hooks?.afterLoad?.({
                    definition: this.definition,
                    repository: this.repository,
                    entity,
                    mode: this.mode,
                    apiContext: this.apiContext,
                    router: this.$router as unknown as CrudRouter,
                    reload: () => this.load(),
                });
            } catch (error) {
                this.loadError = true;
                this.definition.hooks?.onError?.({
                    error,
                    operation: 'detail.load',
                });
            } finally {
                this.loading = false;
            }
        },

        fieldComponent(field: CrudField): string {
            const components: Record<string, string> = {
                text: 'jetpack-text-field',
                email: 'jetpack-email-field',
                url: 'jetpack-url-field',
                password: 'jetpack-password-field',
                number: 'jetpack-number-field',
                textarea: 'jetpack-textarea',
                checkbox: 'jetpack-checkbox',
                switch: 'jetpack-switch',
                date: 'jetpack-datepicker',
                color: 'jetpack-color-picker',
                'rich-text': 'jetpack-rich-text-editor',
                select: 'jetpack-select',
                'multi-select': 'jetpack-multi-select',
                'entity-select': 'jetpack-entity-select',
                'entity-multi-select': 'jetpack-entity-multi-select',
            };

            if (field.type === 'custom') {
                return field.component;
            }

            const component = components[field.type];
            if (!component) {
                throw new Error(`[FroshJetpack Admin CRUD] ${this.crudId}: unsupported field type "${field.type}"`);
            }

            return component;
        },

        fieldValue(field: CrudField): unknown {
            return this.entity ? getByPath(this.entity, String(field.property)) : null;
        },

        updateField(field: CrudField, value: unknown): void {
            if (this.entity) {
                setByPath(this.entity, String(field.property), value);
                removePropertyError(this.entity, String(field.property));
            }
        },

        fieldCriteria(field: CrudField): CrudCriteria | null {
            if (field.type !== 'entity-select' && field.type !== 'entity-multi-select') {
                return null;
            }

            const criteria = new Shopware.Data.Criteria(1, 25);
            field.criteria?.({ criteria });

            return criteria;
        },

        fieldProps(field: CrudField): Record<string, unknown> {
            const props: Record<string, unknown> = {
                label: this.$t(field.labelSnippet ?? `${this.definition.snippets}.detail.fields.${field.id}`),
                helpText: field.helpTextSnippet ? this.$t(field.helpTextSnippet) : '',
                placeholder: field.placeholderSnippet ? this.$t(field.placeholderSnippet) : '',
                required: field.required ?? false,
                disabled: field.disabled ?? false,
                error: propertyErrorMessage(getPropertyError(this.entity, String(field.property))),
            };

            if (
                field.type === 'text' ||
                field.type === 'email' ||
                field.type === 'url' ||
                field.type === 'password' ||
                field.type === 'number'
            ) {
                Object.assign(props, {
                    min: field.min,
                    max: field.max,
                    step: field.step,
                });
            }

            if (field.type === 'select' || field.type === 'multi-select') {
                props.options = field.options.map((option) => ({
                    value: option.value,
                    label: this.$t(option.labelSnippet),
                    disabled: option.disabled,
                }));
            }

            if (field.type === 'entity-select' || field.type === 'entity-multi-select') {
                Object.assign(props, {
                    entity: field.entity,
                    labelProperty: field.labelProperty ?? 'name',
                    criteria: this.fieldCriteria(field),
                    context: this.apiContext,
                    emptyText: this.$t('frosh-jetpack-crud.association.empty'),
                    loadingText: this.$t('frosh-jetpack-crud.association.loading'),
                    searchPlaceholder: this.$t('frosh-jetpack-crud.association.search'),
                });
            }

            if (field.type === 'custom') {
                Object.assign(props, field.props ?? {}, {
                    entity: this.entity,
                    field,
                });
            }

            return props;
        },

        onFieldLoadError(error: unknown): void {
            this.definition.hooks?.onError?.({
                error,
                operation: 'association.load',
            });
        },

        async save(): Promise<void> {
            if (!this.entity || !this.canSave) {
                return;
            }

            const originalMode = this.mode;
            this.saving = true;
            this.saveError = false;

            try {
                await this.definition.hooks?.beforeSave?.({
                    definition: this.definition,
                    repository: this.repository,
                    entity: this.entity,
                    mode: originalMode,
                    apiContext: this.apiContext,
                    router: this.$router as unknown as CrudRouter,
                    reload: () => this.load(),
                });
                await this.repository.save(this.entity, this.apiContext);

                const entity = await this.repository.get(this.entity.id, this.apiContext, this.detailCriteria());
                if (!entity) {
                    throw new Error(`[FroshJetpack Admin CRUD] ${this.crudId}: saved entity could not be reloaded`);
                }
                this.entity = entity;

                await this.definition.hooks?.afterSave?.({
                    definition: this.definition,
                    repository: this.repository,
                    entity,
                    mode: originalMode,
                    apiContext: this.apiContext,
                    router: this.$router as unknown as CrudRouter,
                    reload: () => this.load(),
                });

                if (originalMode === 'create' && this.definition.routeNames.detail) {
                    await this.$router.replace({
                        name: this.definition.routeNames.detail,
                        params: { id: entity.id },
                    });
                }
            } catch (error) {
                this.saveError = true;
                this.definition.hooks?.onError?.({
                    error,
                    operation: 'detail.save',
                });
            } finally {
                this.saving = false;
            }
        },

        canRunAction(action: CrudDetailAction): boolean {
            const privilege = action.privilege ?? `${this.definition.acl.key}.${action.requiredRole ?? 'editor'}`;

            return (this.acl as AclService).can(privilege);
        },

        async runDetailAction(action: CrudDetailAction): Promise<void> {
            if (!this.entity || !this.canRunAction(action)) {
                return;
            }

            this.runningAction = action.id;
            this.actionError = false;
            try {
                await action.handler({
                    definition: this.definition,
                    repository: this.repository,
                    entity: this.entity,
                    mode: this.mode,
                    apiContext: this.apiContext,
                    router: this.$router as unknown as CrudRouter,
                    reload: () => this.load(),
                });
            } catch (error) {
                this.actionError = true;
                this.definition.hooks?.onError?.({
                    error,
                    operation: `detail.action.${action.id}`,
                });
            } finally {
                this.runningAction = null;
            }
        },

        async changeLanguage(value: string | number | boolean | null): Promise<void> {
            if (this.mode === 'create' || typeof value !== 'string' || value === this.languageId) {
                return;
            }

            this.languageId = value;
            await this.load();
        },

        cancel(): void {
            if (this.definition.routeNames.list) {
                void this.$router.push({
                    name: this.definition.routeNames.list,
                });
            } else {
                this.$router.back();
            }
        },
    },
});
