Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'additional_permissions',
    parent: null,
    key: 'frosh_jetpack_configuration',
    roles: {
        viewer: {
            privileges: ['frosh_jetpack_configuration:read'],
            dependencies: [],
        },
        editor: {
            privileges: [
                'frosh_jetpack_configuration:create',
                'frosh_jetpack_configuration:update',
                'frosh_jetpack_configuration:delete',
            ],
            dependencies: ['frosh_jetpack_configuration.viewer'],
        },
    },
});
