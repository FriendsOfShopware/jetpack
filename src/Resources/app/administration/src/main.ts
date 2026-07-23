type BootstrapState = {
    apiVersion: number;
    ready: Promise<void>;
    applicationStartPatched: boolean;
};

type BootstrapHost = typeof globalThis & {
    [key: symbol]: BootstrapState | undefined;
};

const bootstrapKey = Symbol.for('frosh.jetpack.administration.bootstrap');
const host = globalThis as BootstrapHost;
let state = host[bootstrapKey];

if (!state) {
    state = {
        apiVersion: 1,
        applicationStartPatched: false,
        ready: import('./bootstrap').then(({ bootstrap }) => bootstrap()),
    };
    host[bootstrapKey] = state;
} else if (state.apiVersion !== 1) {
    throw new Error(`[FroshJetpack] Bootstrap API ${state.apiVersion} is incompatible with API 1`);
}

const activeState = state;

if (!activeState.applicationStartPatched && typeof Shopware !== 'undefined') {
    const application = Shopware.Application as unknown as {
        start: (...args: unknown[]) => unknown;
    };
    const originalStart = application.start.bind(application);
    application.start = (...args: unknown[]) => activeState.ready.then(() => originalStart(...args));
    activeState.applicationStartPatched = true;
}
