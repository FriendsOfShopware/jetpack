const path = require("node:path");

const shopwareAdministration = path.resolve(
    __dirname,
    "../../../../../../../src/Administration/Resources/app/administration",
);

module.exports = {
    rootDir: __dirname,
    testEnvironment: "jsdom",
    setupFiles: ["<rootDir>/test/setup.js"],
    transform: {
        "^.+\\.tsx?$": [
            path.join(shopwareAdministration, "node_modules/@swc/jest"),
            {
                jsc: {
                    parser: { syntax: "typescript" },
                    target: "es2021",
                },
                module: { type: "commonjs" },
            },
        ],
        "^.+\\.(html\\.twig|twig)$": path.join(
            shopwareAdministration,
            "test/transformer/twigToVueTransformer.js",
        ),
    },
    moduleNameMapper: {
        "^vue$": path.join(
            shopwareAdministration,
            "node_modules/vue/dist/vue.cjs.js",
        ),
        "^@vue/test-utils$": path.join(
            shopwareAdministration,
            "node_modules/@vue/test-utils",
        ),
        "\\.(css|less|scss)$": path.join(
            shopwareAdministration,
            "test/_mocks_/styleMock.js",
        ),
    },
    testMatch: ["<rootDir>/src/**/*.spec.ts"],
};
