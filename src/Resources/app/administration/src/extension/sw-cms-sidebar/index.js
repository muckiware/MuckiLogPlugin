import template from './sw-cms-sidebar.html.twig';
import './sw-cms-sidebar.scss';

Shopware.Component.override('sw-cms-sidebar', {
    template,

    inject: ['acl', 'muwaChangeLogService'],

    data() {
        return {
            muwaChangeLogEnabled: false,
        };
    },

    computed: {
        muwaShowChangeLog() {
            return this.muwaChangeLogEnabled && !!this.page?.id && !this.page?._isNew;
        },
    },

    created() {
        this.muwaLoadChangeLogVisibility();
    },

    methods: {
        async muwaLoadChangeLogVisibility() {
            if (!this.acl.can('muwa_change_log.viewer')) {
                this.muwaChangeLogEnabled = false;
                return;
            }
            this.muwaChangeLogEnabled = await this.muwaChangeLogService.isEnabledFor('cms_page');
        },
    },
});
