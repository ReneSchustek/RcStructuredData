import template from './sw-landing-page-detail-base.html.twig';

/**
 * Hängt die Schema-Diagnose-Karte an die Landingpage-Detailseite.
 */
Shopware.Component.override('sw-landing-page-detail-base', {
    template,
});
