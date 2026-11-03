import template from './sw-product-detail.html.twig';
import changeLogTabOverride from '../muwa-change-log-tab';

Shopware.Component.override('sw-product-detail', changeLogTabOverride('product', template));
