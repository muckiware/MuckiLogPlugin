import template from './muwa-change-log-diff.html.twig';
import './muwa-change-log-diff.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

// Shared by all diff instances so the languages are loaded only once.
let localeCodesPromise = null;

Component.register('muwa-change-log-diff', {
    template,

    inject: ['repositoryFactory'],

    props: {
        changes: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            localeCodes: {},
        };
    },

    computed: {
        rows() {
            return this.changes.map((change, index) => ({
                key: `${index}-${change.field}`,
                heading: this.headingFor(change),
                status: this.statusFor(change),
                oldText: this.lineValue(change.old),
                newText: this.lineValue(change.new),
            }));
        },
    },

    created() {
        this.loadLocaleCodes();
    },

    methods: {
        async loadLocaleCodes() {
            if (!this.changes.some((change) => change.context?.languageId)) {
                return;
            }
            if (localeCodesPromise === null) {
                localeCodesPromise = this.fetchLocaleCodes().catch(() => {
                    localeCodesPromise = null;
                    return {};
                });
            }
            this.localeCodes = await localeCodesPromise;
        },

        async fetchLocaleCodes() {
            const criteria = new Criteria(1, 500);
            criteria.addAssociation('locale');
            const languages = await this.repositoryFactory
                .create('language')
                .search(criteria, Shopware.Context.api);

            const codes = {};
            languages.forEach((language) => {
                codes[language.id] = language.locale?.code ?? language.name;
            });

            return codes;
        },

        headingFor(change) {
            const label = this.fieldLabel(change.field);
            const suffix = this.contextLabel(change);

            return suffix ? `${label} (${suffix})` : label;
        },

        contextLabel(change) {
            const context = change.context ?? {};
            if (context.languageId) {
                return this.localeCodes[context.languageId] ?? context.languageId;
            }
            if (context.linkedEntity) {
                const linked = this.entityLabel(context.linkedEntity);
                return `${this.$tc('muwa-change-log.diff.linkedEntity')} ${linked}`;
            }
            if (change.subEntity) {
                return this.entityLabel(change.subEntity);
            }

            return '';
        },

        statusFor(change) {
            if (change.masked) {
                return '';
            }
            if (this.isEmpty(change.old) && !this.isEmpty(change.new)) {
                return this.$tc('muwa-change-log.diff.added');
            }
            if (!this.isEmpty(change.old) && this.isEmpty(change.new)) {
                return this.$tc('muwa-change-log.diff.removed');
            }

            return '';
        },

        lineValue(value) {
            if (this.isEmpty(value)) {
                return null;
            }

            return this.formatValue(value);
        },

        formatValue(value) {
            if (typeof value === 'boolean') {
                return this.$tc(value ? 'muwa-change-log.diff.yes' : 'muwa-change-log.diff.no');
            }
            if (typeof value === 'object') {
                return JSON.stringify(value);
            }

            return String(value);
        },

        isEmpty(value) {
            return value === null || value === undefined;
        },

        fieldLabel(field) {
            const key = `muwa-change-log.fields.${field}`;
            if (this.$te(key)) {
                return this.$tc(key);
            }
            const words = String(field).replace(/([a-z0-9])([A-Z])/g, '$1 $2').toLowerCase();

            return words.charAt(0).toUpperCase() + words.slice(1);
        },

        entityLabel(entityName) {
            const key = `global.entities.${entityName}`;

            return this.$te(key) ? this.$tc(key, 1) : entityName;
        },
    },
});
