import type { Component, ComponentOptions, defineComponent } from "vue";

declare global {
    const Shopware: {
        Component: {
            // The standalone viewer implements only the two component-factory methods used by Jetpack.
            register(name: string, configuration: ComponentOptions): Component;
            register(
                name: string,
                configuration: () => Promise<
                    Component | { default: Component }
                >,
            ): Component;
            wrapComponentConfig: typeof defineComponent;
        };
        Context: {
            api: Record<string, unknown>;
            app: {
                config: {
                    locale: string;
                };
            };
        };
        Data: {
            // Criteria is deliberately structural here; the runtime fixture is defined in shopware-bootstrap.
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            Criteria: any;
        };
    };
}

export {};
