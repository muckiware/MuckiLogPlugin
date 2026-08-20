import './page/muwa-log-viewer-index';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('muwa-log-viewer', {
    type: 'plugin',
    name: 'muwa-log-viewer',
    title: 'muwa-log-viewer.general.mainMenuItemGeneral',
    description: 'muwa-log-viewer.general.description',
    color: '#9AA8B5',
    icon: 'regular-file-text',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        index: {
            component: 'muwa-log-viewer-index',
            path: 'index',
            meta: {
                privilege: 'muwa_log_viewer.viewer',
            },
        },
    },

    settingsItem: [{
        group: 'plugins',
        to: 'muwa.log.viewer.index',
        icon: 'regular-file-text',
        name: 'muwa-log-viewer',
        label: 'muwa-log-viewer.general.mainMenuItemGeneral',
        privilege: 'muwa_log_viewer.viewer',
    }],
});
