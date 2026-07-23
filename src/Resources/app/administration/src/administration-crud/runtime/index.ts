import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Component.register('frosh-jetpack-crud-list', () => import('../listing'));
Shopware.Component.register('frosh-jetpack-crud-detail', () => import('../detail'));

Shopware.Module.register('frosh-jetpack-crud-runtime', {
    type: 'plugin',
    name: 'frosh-jetpack-crud-runtime',
    title: 'frosh-jetpack-crud.general.title',
    description: 'frosh-jetpack-crud.general.description',
    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },
    routes: {},
});
