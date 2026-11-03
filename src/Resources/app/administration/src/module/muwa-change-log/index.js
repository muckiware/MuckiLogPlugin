import './component/muwa-change-log-diff';
import './component/muwa-change-log-list';
import './page/muwa-change-log-index';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

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
