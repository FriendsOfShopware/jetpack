import {
    defineAsyncComponent,
    defineComponent,
    h,
    type App,
    type Component,
    type ComponentOptions,
} from "vue";

type ComponentModule = Component | { default: Component };
type AsyncComponentConfiguration = () => Promise<ComponentModule>;

const componentRegistry = new Map<string, Component>();

function registerComponent(
    name: string,
    configuration: ComponentOptions | AsyncComponentConfiguration,
): Component {
    const component =
        typeof configuration === "function"
            ? defineAsyncComponent(async () => {
                  const loaded = await configuration();

                  return "default" in loaded ? loaded.default : loaded;
              })
            : defineComponent(configuration);

    componentRegistry.set(name, component);

    return component;
}

class StorybookCriteria {
    public page: number;

    public limit: number | null;

    public term = "";

    public sortings: Array<{ field: string; order: string }> = [];

    public constructor(page = 1, limit: number | null = 25) {
        this.page = page;
        this.limit = limit;
    }

    public static fromCriteria(criteria: StorybookCriteria): StorybookCriteria {
        const copy = new StorybookCriteria(criteria.page, criteria.limit);
        copy.term = criteria.term;
        copy.sortings = [...criteria.sortings];

        return copy;
    }

    public static sort(
        field: string,
        order = "ASC",
    ): {
        field: string;
        order: string;
    } {
        return { field, order };
    }

    public setPage(page: number): this {
        this.page = page;

        return this;
    }

    public setLimit(limit: number): this {
        this.limit = limit;

        return this;
    }

    public setTerm(term: string): this {
        this.term = term;

        return this;
    }

    public addSorting(sorting: { field: string; order: string }): this {
        this.sortings.push(sorting);

        return this;
    }
}

const entityFixtures: Record<string, Array<Record<string, unknown>>> = {
    product: [
        { id: "product-1", name: "Mountain backpack", active: true, stock: 42 },
        { id: "product-2", name: "Travel bottle", active: true, stock: 18 },
        { id: "product-3", name: "Wool blanket", active: false, stock: 7 },
    ],
    language: [
        { id: "language-en", name: "English" },
        { id: "language-de", name: "Deutsch" },
    ],
};

const repositoryFactory = {
    create(entity: string) {
        return {
            search(criteria: StorybookCriteria) {
                const allEntities =
                    entityFixtures[entity] ?? entityFixtures.product;
                const term = criteria.term.toLocaleLowerCase();
                const filtered = term
                    ? allEntities.filter((item) =>
                          String(item.name ?? item.id)
                              .toLocaleLowerCase()
                              .includes(term),
                      )
                    : allEntities;
                const result = [...filtered] as Array<
                    Record<string, unknown>
                > & {
                    total: number;
                };
                result.total = filtered.length;

                return Promise.resolve(result);
            },
        };
    },
};

Object.defineProperty(globalThis, "Shopware", {
    configurable: true,
    value: {
        Component: {
            register: registerComponent,
            wrapComponentConfig: defineComponent,
        },
        Context: {
            api: {},
            app: {
                config: {
                    locale: "en-GB",
                },
            },
        },
        Data: {
            Criteria: StorybookCriteria,
        },
    },
});

export const componentsReady = import("../src/component/jetpack-ui");

export function installJetpackStorybook(app: App): void {
    componentRegistry.forEach((component, name) => {
        app.component(name, component);
    });

    app.component(
        "router-link",
        defineComponent({
            inheritAttrs: false,
            props: {
                to: {
                    type: [String, Object],
                    required: true,
                },
            },
            setup(_props, { attrs, slots }) {
                return () => h("a", { ...attrs, href: "#" }, slots.default?.());
            },
        }),
    );

    app.provide("repositoryFactory", repositoryFactory);
}
