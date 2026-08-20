import './module/muwa-log-viewer';
import './service/muwa-log-viewer.api.service';

Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'muwa_log_viewer',
    roles: {
        viewer: {
            privileges: ['muwa_log_viewer:read'],
            dependencies: [],
        },
    },
});
