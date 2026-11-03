import template from './muwa-change-log-detail-tab.html.twig';

/**
 * Child route view of the "Änderungsverlauf" tab on detail pages.
 * The entity name comes from the route meta set in the module's routeMiddleware.
 */
Shopware.Component.register('muwa-change-log-detail-tab', {
    template,

    // Detail pages pass their own props (product-id, customer, …) to the router view.
    inheritAttrs: false,

    computed: {
        entityName() {
            return this.$route.meta?.muwaChangeLogEntity ?? null;
        },

        entityId() {
            return this.$route.params?.id ?? null;
        },
    },
});
