import template from './sw-cms-el-config-youtube-video.html.twig';

const { Component } = Shopware;

/**
 * Ergänzt das Core-Konfigurationsformular des youtube-video-Elements um vier Schema.org-Felder.
 *
 * Die Werte landen in der Slot-fieldConfig (rcSchemaName, rcSchemaDescription, rcSchemaUploadDate,
 * rcSchemaDuration) und werden storefront-seitig vom YoutubeVideoExtractor gelesen. Fehlende Keys
 * werden beim ersten Schreiben als statische Config-Einträge initialisiert.
 */
Component.override('sw-cms-el-config-youtube-video', {
    template,

    computed: {
        rcSchemaName: {
            get() {
                return this.rcSchemaFieldValue('rcSchemaName');
            },
            set(value) {
                this.setRcSchemaFieldValue('rcSchemaName', value);
            },
        },
        rcSchemaDescription: {
            get() {
                return this.rcSchemaFieldValue('rcSchemaDescription');
            },
            set(value) {
                this.setRcSchemaFieldValue('rcSchemaDescription', value);
            },
        },
        rcSchemaUploadDate: {
            get() {
                return this.rcSchemaFieldValue('rcSchemaUploadDate');
            },
            set(value) {
                this.setRcSchemaFieldValue('rcSchemaUploadDate', value);
            },
        },
        rcSchemaDuration: {
            get() {
                return this.rcSchemaFieldValue('rcSchemaDuration');
            },
            set(value) {
                this.setRcSchemaFieldValue('rcSchemaDuration', value);
            },
        },
    },

    methods: {
        rcSchemaFieldValue(key) {
            const config = this.element.config[key];

            return config ? config.value : '';
        },

        setRcSchemaFieldValue(key, value) {
            if (!this.element.config[key]) {
                this.element.config[key] = { source: 'static', value };
            } else {
                this.element.config[key].value = value;
            }

            this.$emit('element-update', this.element);
        },
    },
});
