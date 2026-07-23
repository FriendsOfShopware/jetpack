import template from './listing.html.twig';
import { applyAssociations, collectListingAssociations } from '../association/association';
import { createApiContext, initialLanguageId } from '../compatibility/host';
import type {
    CrudApiContext,
    CrudBulkAction,
    CrudColumn,
    CrudCriteria,
    CrudEntity,
    CrudListingDefinition,
    CrudRepository,
    CrudRouter,
    CrudRowAction,
    ResolvedCrudDefinition,
} from '../types';

type EntitySearchResult = CrudEntity[] & { total?: number };

type EntityRepository = CrudRepository<CrudEntity>;

type RepositoryFactory = {
    create: (entity: string) => EntityRepository;
};

type AclService = {
    can: (privilege: string) => boolean;
};

type EntityTable = {
    load: () => Promise<void>;
};

export default Shopware.Component.wrapComponentConfig({
    template,
    inject: [
        'repositoryFactory',
        'acl',
    ],

    props: {
        crudId: { type: String, required: true },
    },

    data() {
        return {
            term: '',
            total: 0,
            loadError: false,
            deleteError: false,
            deleting: false,
            pendingDelete: null as CrudEntity | null,
            selectedIds: [] as string[],
            currentEntities: [] as CrudEntity[],
            languageId: initialLanguageId(),
            runningRowAction: null as string | null,
            rowActionError: false,
            pendingBulkAction: null as CrudBulkAction | 'delete' | null,
            bulkBusy: false,
            bulkError: false,
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

        listing(): CrudListingDefinition {
            if (this.definition.listing === false) {
                throw new Error(`[FroshJetpack Admin CRUD] ${this.crudId}: listing is disabled`);
            }

            return this.definition.listing;
        },

        repository(): EntityRepository {
            return (this.repositoryFactory as RepositoryFactory).create(this.definition.entity);
        },

        apiContext(): CrudApiContext {
            return createApiContext(this.languageId);
        },

        translationEnabled(): boolean {
            return this.definition.translation !== false && this.definition.translation?.enabled === true;
        },

        columns(): CrudColumn[] {
            return this.listing.columns.map((column) => ({
                ...column,
                label: this.$t(column.labelSnippet ?? `${this.definition.snippets}.list.columns.${column.id}`),
            }));
        },

        criteria(): CrudCriteria {
            const criteria = new Shopware.Data.Criteria(1, this.listing.pageSize ?? 25);
            criteria.setTerm(this.term);
            applyAssociations(criteria, collectListingAssociations(this.listing));
            this.listing.defaultSort?.forEach((sorting) => {
                criteria.addSorting(
                    Shopware.Data.Criteria.sort(sorting.property, sorting.direction, sorting.naturalSorting),
                );
            });
            this.listing.criteria?.({ criteria });

            return criteria;
        },

        canCreate(): boolean {
            return (
                this.definition.detail !== false &&
                this.definition.detail.create !== false &&
                (this.acl as AclService).can(`${this.definition.acl.key}.creator`)
            );
        },

        canEdit(): boolean {
            return this.definition.detail !== false && (this.acl as AclService).can(`${this.definition.acl.key}.editor`);
        },

        canDelete(): boolean {
            return this.listing.allowDelete !== false && (this.acl as AclService).can(`${this.definition.acl.key}.deleter`);
        },

        bulkActions(): CrudBulkAction[] {
            return this.listing.bulk === false ? [] : (this.listing.bulk?.actions ?? []);
        },

        bulkDeleteEnabled(): boolean {
            return this.listing.bulk !== false && this.listing.bulk?.delete === true;
        },

        selectable(): boolean {
            return this.bulkDeleteEnabled || this.bulkActions.length > 0;
        },
    },

    methods: {
        async reload(): Promise<void> {
            this.loadError = false;
            await (this.$refs.table as EntityTable | undefined)?.load();
        },

        onLoad(result: EntitySearchResult): void {
            this.total = result.total ?? result.length;
            this.currentEntities = Array.from(result);
            const loadedIds = new Set(this.currentEntities.map((entity) => entity.id));
            this.selectedIds = this.selectedIds.filter((id) => loadedIds.has(id));
            this.loadError = false;
        },

        onLoadError(error: unknown): void {
            this.loadError = true;
            this.definition.hooks?.onError?.({
                error,
                operation: 'listing.load',
            });
        },

        openCreate(): void {
            if (this.definition.routeNames.create) {
                void this.$router.push({
                    name: this.definition.routeNames.create,
                });
            }
        },

        openDetail(entity: CrudEntity): void {
            if (this.definition.routeNames.detail) {
                void this.$router.push({
                    name: this.definition.routeNames.detail,
                    params: { id: entity.id },
                });
            }
        },

        askDelete(entity: CrudEntity): void {
            this.pendingDelete = entity;
            this.deleteError = false;
        },

        closeDelete(): void {
            if (!this.deleting) {
                this.pendingDelete = null;
            }
        },

        async confirmDelete(): Promise<void> {
            if (!this.pendingDelete) {
                return;
            }

            this.deleting = true;
            this.deleteError = false;

            try {
                await this.repository.delete(this.pendingDelete.id, this.apiContext);
                this.pendingDelete = null;
                await this.reload();
            } catch (error) {
                this.deleteError = true;
                this.definition.hooks?.onError?.({
                    error,
                    operation: 'listing.delete',
                });
            } finally {
                this.deleting = false;
            }
        },

        currentLocale(): string {
            const app = Shopware.Context.app as unknown as {
                config?: { locale?: string };
                systemCurrencyISOCode?: string;
            };

            return app.config?.locale ?? 'en-GB';
        },

        formatColumnValue(column: CrudColumn, value: unknown): string {
            if (value === null || value === undefined) {
                return '';
            }

            if (column.type === 'boolean') {
                return this.$t(value ? 'frosh-jetpack-crud.values.yes' : 'frosh-jetpack-crud.values.no');
            }

            if (column.type === 'number' && typeof value === 'number') {
                return new Intl.NumberFormat(this.currentLocale()).format(value);
            }

            if (column.type === 'currency' && typeof value === 'number') {
                const app = Shopware.Context.app as unknown as { systemCurrencyISOCode?: string };
                return new Intl.NumberFormat(this.currentLocale(), {
                    style: 'currency',
                    currency: column.currency ?? app.systemCurrencyISOCode ?? 'EUR',
                }).format(value);
            }

            if (column.type === 'date' && (typeof value === 'string' || value instanceof Date)) {
                const date = value instanceof Date ? value : new Date(value);
                return Number.isNaN(date.getTime())
                    ? typeof value === 'string'
                        ? value
                        : value.toString()
                    : new Intl.DateTimeFormat(this.currentLocale(), {
                          dateStyle: 'medium',
                          timeStyle: 'short',
                      }).format(date);
            }

            if (typeof value === 'string') {
                return value;
            }
            if (typeof value === 'number' || typeof value === 'boolean' || typeof value === 'bigint') {
                return value.toString();
            }

            return JSON.stringify(value) ?? '';
        },

        valueKey(value: unknown): string {
            return typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean'
                ? value.toString()
                : (JSON.stringify(value) ?? '');
        },

        badgeLabel(column: CrudColumn, value: unknown): string {
            const badge = column.badges?.[this.valueKey(value)];

            return badge?.labelSnippet ? this.$t(badge.labelSnippet) : this.formatColumnValue(column, value);
        },

        badgeVariant(column: CrudColumn, value: unknown): string {
            return column.badges?.[this.valueKey(value)]?.variant ?? 'neutral';
        },

        canRunAction(action: CrudRowAction | CrudBulkAction): boolean {
            const privilege = action.privilege ?? `${this.definition.acl.key}.${action.requiredRole ?? 'editor'}`;

            return (this.acl as AclService).can(privilege);
        },

        async runRowAction(action: CrudRowAction, entity: CrudEntity): Promise<void> {
            if (!this.canRunAction(action)) {
                return;
            }

            this.runningRowAction = `${action.id}:${entity.id}`;
            this.rowActionError = false;
            try {
                await action.handler({
                    definition: this.definition,
                    repository: this.repository,
                    entity,
                    apiContext: this.apiContext,
                    router: this.$router as unknown as CrudRouter,
                    reload: () => this.reload(),
                });
            } catch (error) {
                this.rowActionError = true;
                this.definition.hooks?.onError?.({
                    error,
                    operation: `listing.action.${action.id}`,
                });
            } finally {
                this.runningRowAction = null;
            }
        },

        askBulkDelete(): void {
            if (this.selectedIds.length > 0 && this.canDelete) {
                this.pendingBulkAction = 'delete';
                this.bulkError = false;
            }
        },

        async askBulkAction(action: CrudBulkAction): Promise<void> {
            if (this.selectedIds.length === 0 || !this.canRunAction(action)) {
                return;
            }

            if (action.confirmation) {
                this.pendingBulkAction = action;
                this.bulkError = false;
                return;
            }

            await this.executeBulkAction(action);
        },

        closeBulkAction(): void {
            if (!this.bulkBusy) {
                this.pendingBulkAction = null;
            }
        },

        bulkTitle(): string {
            return this.pendingBulkAction === 'delete'
                ? this.$t('frosh-jetpack-crud.bulk.deleteTitle')
                : this.$t(this.pendingBulkAction?.confirmation?.titleSnippet ?? 'frosh-jetpack-crud.bulk.confirmTitle');
        },

        bulkMessage(): string {
            return this.pendingBulkAction === 'delete'
                ? this.$t('frosh-jetpack-crud.bulk.deleteMessage', { count: this.selectedIds.length })
                : this.$t(this.pendingBulkAction?.confirmation?.messageSnippet ?? 'frosh-jetpack-crud.bulk.confirmMessage', {
                      count: this.selectedIds.length,
                  });
        },

        async confirmBulkAction(): Promise<void> {
            if (this.pendingBulkAction === 'delete') {
                await this.executeBulkDelete();
                return;
            }
            if (this.pendingBulkAction) {
                await this.executeBulkAction(this.pendingBulkAction);
            }
        },

        async executeBulkDelete(): Promise<void> {
            this.bulkBusy = true;
            this.bulkError = false;
            try {
                if (this.repository.syncDeleted) {
                    await this.repository.syncDeleted(this.selectedIds, this.apiContext);
                } else {
                    await Promise.all(this.selectedIds.map((id) => this.repository.delete(id, this.apiContext)));
                }
                await this.completeBulkAction();
            } catch (error) {
                this.handleBulkError(error, 'delete');
            } finally {
                this.bulkBusy = false;
            }
        },

        async executeBulkAction(action: CrudBulkAction): Promise<void> {
            this.bulkBusy = true;
            this.bulkError = false;
            try {
                const selected = new Set(this.selectedIds);
                await action.handler({
                    definition: this.definition,
                    repository: this.repository,
                    selectedIds: [...this.selectedIds],
                    entities: this.currentEntities.filter((entity) => selected.has(entity.id)),
                    apiContext: this.apiContext,
                    router: this.$router as unknown as CrudRouter,
                    reload: () => this.reload(),
                });
                await this.completeBulkAction();
            } catch (error) {
                this.handleBulkError(error, action.id);
            } finally {
                this.bulkBusy = false;
            }
        },

        async completeBulkAction(): Promise<void> {
            this.pendingBulkAction = null;
            this.selectedIds = [];
            await this.reload();
        },

        handleBulkError(error: unknown, action: string): void {
            this.bulkError = true;
            this.definition.hooks?.onError?.({
                error,
                operation: `listing.bulk.${action}`,
            });
        },

        async changeLanguage(value: string | number | boolean | null): Promise<void> {
            if (typeof value !== 'string' || value === this.languageId) {
                return;
            }

            this.languageId = value;
            await this.reload();
        },
    },
});
