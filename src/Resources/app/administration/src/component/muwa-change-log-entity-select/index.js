import template from './muwa-change-log-entity-select.html.twig';

const OWN_ENTITY = 'muwa_admin_change_log';
// Mirrors ChangeLogConfig::DEFAULT_ENTITIES; used while nothing is saved yet.
const DEFAULT_ENTITIES = ['product', 'category', 'order', 'customer', 'cms_page'];

/**
 * Multi-select for MuckiLogPlugin.config.changeLogEntities.
 * sw-system-config binds it with `value` / `update:value` (6.6 and 6.7).
 */
Shopware.Component.register('muwa-change-log-entity-select', {
    template,

    inject: ['muwaChangeLogService'],

    emits: ['update:value'],

    props: {
        value: {
            type: [Array, String],
            required: false,
            default: null,
        },
        // Passed by sw-form-field-renderer from config.xml; consumed here so it
        // does not end up as a DOM attribute.
        defaultValue: {
            type: [Array, String],
            required: false,
            default: null,
        },
        componentName: {
            type: String,
            required: false,
            default: null,
        },
    },

    computed: {
        selectedEntities() {
            const value = this.normalize(this.value);

            return value ?? this.normalize(this.defaultValue) ?? [...DEFAULT_ENTITIES];
        },

        entityOptions() {
            const registry = Shopware.EntityDefinition.getDefinitionRegistry();
            const names = new Set(Array.from(registry.keys()).filter((name) => this.isSelectable(name, registry.get(name))));
            // Keep saved entries selectable even if the schema no longer knows them.
            this.selectedEntities.forEach((name) => names.add(name));

            return Array.from(names)
                .map((name) => ({ value: name, label: this.entityLabel(name) }))
                .sort((a, b) => a.label.localeCompare(b.label));
        },
    },

    beforeUnmount() {
        // Settings may have been saved; detail tabs should read them fresh.
        this.muwaChangeLogService.clearSettingsCache();
    },

    methods: {
        normalize(value) {
            if (Array.isArray(value)) {
                return value.filter((name) => typeof name === 'string' && name !== '');
            }
            if (typeof value !== 'string' || value === '') {
                return null;
            }
            try {
                const parsed = JSON.parse(value);

                return Array.isArray(parsed) ? this.normalize(parsed) : null;
            } catch {
                return null;
            }
        },

        isSelectable(name, definition) {
            if (name === OWN_ENTITY || name.endsWith('_translation') || name.endsWith('_mapping')) {
                return false;
            }

            // Mapping definitions (product_category, order_tag, …) have no own id field.
            return !!definition?.properties?.id;
        },

        entityLabel(name) {
            if (name === 'system_config') {
                return this.$tc('muwa-change-log.list.systemConfigEntity');
            }
            const key = `global.entities.${name}`;

            return this.$te(key) ? this.$tc(key, 1) : name;
        },

        onChange(value) {
            this.$emit('update:value', Array.isArray(value) ? value : []);
        },
    },
});
