import './component/muwa-change-log-diff';
import './component/muwa-change-log-list';
import './page/muwa-change-log-index';
import './view/muwa-change-log-detail-tab';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

const TAB_ROUTE_SUFFIX = 'muwa-change-log';
// Detail route name => tracked entity shown in its "Änderungsverlauf" tab.
const DETAIL_ROUTES = {
    'sw.product.detail': { entity: 'product', parentPath: 'sw.product.index' },
    'sw.category.detail': { entity: 'category', parentPath: 'sw.category.index' },
    'sw.order.detail': { entity: 'order', parentPath: 'sw.order.index' },
    'sw.customer.detail': { entity: 'customer', parentPath: 'sw.customer.index' },
};

function addChangeLogTabRoute(route) {
    const target = DETAIL_ROUTES[route.name];
    if (!target || !Array.isArray(route.children)) {
        return;
    }
    const name = `${route.name}.${TAB_ROUTE_SUFFIX}`;
    if (route.children.some((child) => child.name === name)) {
        return;
    }
    route.children.push({
        name,
        path: `${route.path}/${TAB_ROUTE_SUFFIX}`,
        component: 'muwa-change-log-detail-tab',
        isChildren: true,
        meta: {
            parentPath: target.parentPath,
            privilege: 'muwa_change_log.viewer',
            muwaChangeLogEntity: target.entity,
        },
    });
}

Module.register('muwa-change-log', {
    type: 'plugin',
    name: 'muwa-change-log',
    title: 'muwa-change-log.general.mainMenuItemGeneral',
    description: 'muwa-change-log.general.description',
    color: '#9AA8B5',
    icon: 'regular-history',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routeMiddleware(next, currentRoute) {
        addChangeLogTabRoute(currentRoute);
        next(currentRoute);
    },

    routes: {
        index: {
            component: 'muwa-change-log-index',
            path: 'index',
            meta: {
                privilege: 'muwa_change_log.viewer',
            },
        },
    },

    settingsItem: [{
        group: 'plugins',
        to: 'muwa.change.log.index',
        icon: 'regular-history',
        name: 'muwa-change-log',
        label: 'muwa-change-log.general.mainMenuItemGeneral',
        privilege: 'muwa_change_log.viewer',
    }],
});
