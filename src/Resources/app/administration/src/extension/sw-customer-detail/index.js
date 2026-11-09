import template from './sw-customer-detail.html.twig';
import changeLogTabOverride from '../muwa-change-log-tab';

Shopware.Component.override('sw-customer-detail', changeLogTabOverride('customer', template));
