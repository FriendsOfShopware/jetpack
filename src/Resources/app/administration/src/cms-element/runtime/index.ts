import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Component.register('frosh-jetpack-cms-element', () => import('./component'));
Shopware.Component.register('frosh-jetpack-cms-element-config', () => import('./config'));
Shopware.Component.register('frosh-jetpack-cms-element-preview', () => import('./preview'));

Shopware.Module.register('frosh-jetpack-cms-runtime', {
    type: 'plugin',
    name: 'frosh-jetpack-cms-runtime',
    title: 'frosh-jetpack-cms.preview.subtitle',
    description: 'frosh-jetpack-cms.preview.subtitle',
    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },
    routes: {},
});
