import template from './sw-category-view.html.twig';
import changeLogTabOverride from '../muwa-change-log-tab';

// The category detail tabs live in sw-category-view (6.6 and 6.7).
Shopware.Component.override('sw-category-view', changeLogTabOverride('category', template));
