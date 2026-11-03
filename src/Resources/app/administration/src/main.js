import './module/muwa-log-viewer';
import './module/muwa-change-log';
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

Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'muwa_change_log',
    roles: {
        viewer: {
            privileges: [
                'muwa_admin_change_log:read',
                'user:read',
                'integration:read',
                'sales_channel:read',
                'language:read',
            ],
            dependencies: [],
        },
    },
});
