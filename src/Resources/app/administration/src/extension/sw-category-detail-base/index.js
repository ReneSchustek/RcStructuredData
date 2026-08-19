import template from './sw-category-detail-base.html.twig';

/**
 * Hängt die Schema-Diagnose-Karte an die Kategorie-Detailseite (nur für Seiten-Kategorien).
 */
Shopware.Component.override('sw-category-detail-base', {
    template,
});
