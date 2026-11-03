import template from './muwa-change-log-list.html.twig';
import './muwa-change-log-list.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

const SOURCE_ALL = { type: 'all', id: null };
const SOURCE_FIELDS = {
    user: 'userId',
    integration: 'integrationId',
    sales_channel: 'salesChannelId',
};
const ACTION_VARIANTS = {
    insert: 'success',
    update: 'info',
    delete: 'danger',
};
const OPTION_LIMIT = 500;

Component.register('muwa-change-log-list', {
    template,

    inject: ['repositoryFactory', 'acl', 'systemConfigApiService'],

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    props: {
        entityName: {
            type: String,
            required: false,
            default: null,
        },
        entityId: {
            type: String,
            required: false,
            default: null,
        },
        compact: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            items: [],
            total: 0,
            page: 1,
            limit: 25,
            isLoading: false,
            loadError: false,
            term: '',
            dateFrom: null,
            dateTo: null,
            sourceFilter: { ...SOURCE_ALL },
            entityFilter: [],
            entityNames: [],
            sourceOptionsLoaded: [],
            expanded: [],
        };
    },

    computed: {
        changeLogRepository() {
            return this.repositoryFactory.create('muwa_admin_change_log');
        },

        isEntityFixed() {
            return !!this.entityName;
        },

        showEntityColumn() {
            return !this.compact && !this.isEntityFixed;
        },

        columnCount() {
            return 5 + (this.showEntityColumn ? 1 : 0) + (this.compact ? 0 : 1);
        },

        sourceKey() {
            const { type, id } = this.sourceFilter;

            return id ? `${type}:${id}` : type;
        },

        sourceOptions() {
            return [
                { value: 'all', label: this.$tc('muwa-change-log.list.optionAll') },
                { value: 'system', label: this.$tc('muwa-change-log.list.optionSystem') },
                ...this.sourceOptionsLoaded,
            ];
        },

        entityOptions() {
            return this.entityNames
                .map((name) => ({ value: name, label: this.entityLabel(name) }))
                .sort((a, b) => a.label.localeCompare(b.label));
        },
    },

    watch: {
        entityName() {
            this.reload();
        },
        entityId() {
            this.reload();
        },
    },

    created() {
        this.debouncedSearch = Shopware.Utils.debounce((value) => {
            this.term = value ?? '';
            this.reload();
        }, 400);
        this.loadList();
        this.loadSourceOptions();
        if (!this.isEntityFixed) {
            this.loadEntityNames();
        }
    },

    methods: {
        async loadList() {
            this.isLoading = true;
            this.loadError = false;
            try {
                const result = await this.changeLogRepository.search(this.buildCriteria(), Shopware.Context.api);
                this.items = Array.from(result);
                this.total = result.total ?? 0;
            } catch {
                this.items = [];
                this.total = 0;
                this.loadError = true;
                this.createNotificationError({
                    message: this.$tc('muwa-change-log.list.loadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        reload() {
            this.page = 1;
            this.expanded = [];
            this.loadList();
        },

        buildCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));
            criteria.addAssociation('user');
            criteria.setTotalCountMode(1);
            if (this.term) {
                criteria.setTerm(this.term);
            }
            this.addEntityFilters(criteria);
            this.addDateFilter(criteria);
            this.addSourceFilter(criteria);

            return criteria;
        },

        addEntityFilters(criteria) {
            if (this.entityName) {
                criteria.addFilter(Criteria.equals('entityName', this.entityName));
            } else if (this.entityFilter.length) {
                criteria.addFilter(Criteria.equalsAny('entityName', this.entityFilter));
            }
            if (this.entityName && this.entityId) {
                criteria.addFilter(Criteria.equals('entityId', this.entityId));
            }
        },

        addDateFilter(criteria) {
            const range = {};
            if (this.dateFrom) {
                range.gte = this.toDbDate(this.dateFrom, false);
            }
            if (this.dateTo) {
                range.lte = this.toDbDate(this.dateTo, true);
            }
            if (Object.keys(range).length) {
                criteria.addFilter(Criteria.range('createdAt', range));
            }
        },

        addSourceFilter(criteria) {
            const { type, id } = this.sourceFilter;
            if (type === 'system') {
                criteria.addFilter(Criteria.equals('sourceType', 'system'));
                return;
            }
            if (SOURCE_FIELDS[type] && id) {
                criteria.addFilter(Criteria.equals(SOURCE_FIELDS[type], id));
            }
        },

        // Day boundaries in the browser's time zone, sent as UTC in the
        // storage format (created_at is stored in UTC).
        toDbDate(value, endOfDay) {
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return null;
            }
            if (endOfDay) {
                date.setHours(23, 59, 59, 999);
            } else {
                date.setHours(0, 0, 0, 0);
            }

            return date.toISOString().replace('T', ' ').replace('Z', '');
        },

        async loadSourceOptions() {
            const groups = await Promise.all([
                this.loadSourceGroup('user', (user) => this.userName(user)),
                this.loadSourceGroup('integration', (integration) => integration.label),
                this.loadSourceGroup('sales_channel', (channel) => channel.translated?.name ?? channel.name),
            ]);
            this.sourceOptionsLoaded = groups.flat();
        },

        async loadSourceGroup(type, labelOf) {
            if (!this.acl.can(`${type}:read`)) {
                return [];
            }
            try {
                const result = await this.repositoryFactory
                    .create(type)
                    .search(new Criteria(1, OPTION_LIMIT), Shopware.Context.api);
                const suffix = this.$tc(`muwa-change-log.sourceType.${type}`);

                return Array.from(result)
                    .map((entity) => ({ value: `${type}:${entity.id}`, label: `${labelOf(entity) || entity.id} (${suffix})` }))
                    .sort((a, b) => a.label.localeCompare(b.label));
            } catch {
                return [];
            }
        },

        async loadEntityNames() {
            const names = new Set(['system_config', ...(await this.loadTrackedEntities())]);
            try {
                const criteria = new Criteria(1, 1);
                criteria.addAggregation(Criteria.terms('entityNames', 'entityName', OPTION_LIMIT));
                const result = await this.changeLogRepository.search(criteria, Shopware.Context.api);
                (result.aggregations?.entityNames?.buckets ?? []).forEach((bucket) => names.add(bucket.key));
            } catch {
                // Keep the tracked entities only.
            }
            this.entityNames = Array.from(names);
        },

        async loadTrackedEntities() {
            if (!this.acl.can('system_config:read')) {
                return [];
            }
            try {
                const values = await this.systemConfigApiService.getValues('MuckiLogPlugin.config');
                const entities = values?.['MuckiLogPlugin.config.changeLogEntities'];

                return Array.isArray(entities) ? entities : [];
            } catch {
                return [];
            }
        },

        onSearch(value) {
            this.debouncedSearch(value);
        },

        onDateFromChange(value) {
            this.dateFrom = value || null;
            this.reload();
        },

        onDateToChange(value) {
            this.dateTo = value || null;
            this.reload();
        },

        onSourceChange(key) {
            if (!key || key === 'all' || key === 'system') {
                this.sourceFilter = key === 'system' ? { type: 'system', id: null } : { ...SOURCE_ALL };
            } else {
                const [type, id] = key.split(':');
                this.sourceFilter = { type, id };
            }
            this.reload();
        },

        onEntityFilterChange(value) {
            this.entityFilter = Array.isArray(value) ? value : [];
            this.reload();
        },

        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;
            this.expanded = [];
            this.loadList();
        },

        toggle(item) {
            this.expanded = this.isExpanded(item)
                ? this.expanded.filter((id) => id !== item.id)
                : [...this.expanded, item.id];
        },

        isExpanded(item) {
            return this.expanded.includes(item.id);
        },

        formatDate(value) {
            return Shopware.Utils.format.date(value, {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
        },

        actionVariant(action) {
            return ACTION_VARIANTS[action] ?? 'neutral';
        },

        actionLabel(action) {
            return this.$tc(`muwa-change-log.action.${action}`);
        },

        sourceTypeLabel(sourceType) {
            return this.$tc(`muwa-change-log.sourceType.${sourceType}`);
        },

        sourceLabel(item) {
            if (item.sourceType === 'system') {
                return this.$tc('muwa-change-log.list.optionSystem');
            }
            if (item.sourceType === 'user') {
                return (item.user ? this.userName(item.user) : '') || item.userName || item.userId || '–';
            }

            return item.sourceName || item.integrationId || item.salesChannelId || '–';
        },

        userName(user) {
            const fullName = `${user.firstName ?? ''} ${user.lastName ?? ''}`.trim();

            return fullName || user.username;
        },

        entityLabel(entityName) {
            if (entityName === 'system_config') {
                return this.$tc('muwa-change-log.list.systemConfigEntity');
            }
            const key = `global.entities.${entityName}`;

            return this.$te(key) ? this.$tc(key, 1) : entityName;
        },

        changeCount(item) {
            const count = Array.isArray(item.changes) ? item.changes.length : 0;
            // Built in JS: named interpolation in $tc is unreliable in this vue-i18n version.
            const unit = count === 1 ? 'fieldSingular' : 'fieldPlural';

            return `${count} ${this.$tc(`muwa-change-log.list.${unit}`)}`;
        },
    },
});
