import template from './sw-order-detail.html.twig';
import changeLogTabOverride from '../muwa-change-log-tab';

Shopware.Component.override('sw-order-detail', changeLogTabOverride('order', template));
