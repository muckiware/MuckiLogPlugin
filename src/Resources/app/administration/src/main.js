import './module/muwa-log-viewer';
import './module/muwa-change-log';
import './service/muwa-log-viewer.api.service';
import './service/muwa-change-log.api.service';
import './component/muwa-change-log-entity-select';
import './extension/sw-product-detail';
import './extension/sw-category-view';
import './extension/sw-order-detail';
import './extension/sw-customer-detail';
import './extension/sw-cms-sidebar';

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
