import template from './muwa-change-log-index.html.twig';
import './muwa-change-log-index.scss';

const { Component } = Shopware;

Component.register('muwa-change-log-index', {
    template,

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },
});
