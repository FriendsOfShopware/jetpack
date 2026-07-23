import './acl';
import './page/frosh-jetpack-configuration-list';
import './page/frosh-jetpack-configuration-detail';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

type ModuleRoute = { params: Record<string, string | string[]> };

Shopware.Module.register('frosh-jetpack-configuration', {
    type: 'plugin',
    name: 'frosh-jetpack-configuration',
    title: 'frosh-jetpack-configuration.general.title',
    description: 'frosh-jetpack-configuration.general.description',
    color: '#57D9A3',
    icon: 'regular-cog',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        index: {
            component: 'frosh-jetpack-configuration-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'frosh_jetpack_configuration:read',
            },
        },
        detail: {
            component: 'frosh-jetpack-configuration-detail',
            path: 'detail/:bundle',
            props: {
                default: (route: ModuleRoute) => ({ bundle: String(route.params.bundle) }),
            },
            meta: {
                parentPath: 'frosh.jetpack.configuration.index',
                privilege: 'frosh_jetpack_configuration:read',
            },
        },
    },

    settingsItem: {
        group: 'plugins',
        to: 'frosh.jetpack.configuration.index',
        icon: 'regular-cog',
        privilege: 'frosh_jetpack_configuration:read',
        label: 'frosh-jetpack-configuration.general.title',
    },
});
