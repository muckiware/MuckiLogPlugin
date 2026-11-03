/**
 * Shared override options for the "Änderungsverlauf" tab on detail pages.
 * The tab is visible when the user has the change log viewer role, the
 * change log is active and the entity is tracked.
 */
export default function changeLogTabOverride(entityName, template) {
    return {
        template,

        inject: ['acl', 'muwaChangeLogService'],

        data() {
            return {
                muwaChangeLogTabVisible: false,
            };
        },

        computed: {
            muwaChangeLogEntityName() {
                return entityName;
            },
        },

        created() {
            this.muwaLoadChangeLogTabVisibility();
        },

        methods: {
            async muwaLoadChangeLogTabVisibility() {
                if (!this.acl.can('muwa_change_log.viewer')) {
                    this.muwaChangeLogTabVisible = false;
                    return;
                }
                this.muwaChangeLogTabVisible = await this.muwaChangeLogService.isEnabledFor(entityName);
            },
        },
    };
}
