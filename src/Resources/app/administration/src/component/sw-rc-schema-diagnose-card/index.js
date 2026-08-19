import template from './sw-rc-schema-diagnose-card.html.twig';

const { Component } = Shopware;

/**
 * Zeigt an der Kategorie-Detailseite die Schema-Vollständigkeit: Knotenzahl und fehlende
 * Pflicht-/Empfehlungsfelder je @graph-Knoten (aus dem Admin-API-Endpunkt).
 */
Component.register('sw-rc-schema-diagnose-card', {
    template,

    inject: ['rcSchemaDiagnoseService'],

    props: {
        entityId: {
            type: String,
            required: true,
        },
        entityType: {
            type: String,
            required: false,
            default: 'category',
            validator(value) {
                return ['category', 'landingPage'].includes(value);
            },
        },
    },

    data() {
        return {
            isLoading: false,
            hasError: false,
            result: null,
        };
    },

    watch: {
        entityId: {
            immediate: true,
            handler() {
                this.loadDiagnosis();
            },
        },
    },

    methods: {
        loadDiagnosis() {
            if (!this.entityId) {
                return;
            }

            this.isLoading = true;
            this.hasError = false;

            const request = this.entityType === 'landingPage'
                ? this.rcSchemaDiagnoseService.diagnoseLandingPage(this.entityId)
                : this.rcSchemaDiagnoseService.diagnoseCategory(this.entityId);

            request
                .then((result) => {
                    this.result = result;
                })
                .catch(() => {
                    this.hasError = true;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
    },
});
