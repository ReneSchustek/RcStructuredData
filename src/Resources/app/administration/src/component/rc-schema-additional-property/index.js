import template from './rc-schema-additional-property.html.twig';

const { Component } = Shopware;

/**
 * Custom-Field-Renderer für `rc_schema_additional_properties` (Typ json).
 *
 * Pflegt eine Liste von Name/Wert-Paaren als Repeater und schreibt sie exakt im Format
 * `[{name, value}, …]`, das der storefront-seitige `AbstractPageNodeProvider` erwartet.
 * Wird von `sw-form-field-renderer` über `config.componentName` eingebunden (value-Prop rein,
 * `update:value`-Event raus).
 */
Component.register('rc-schema-additional-property', {
    template,

    props: {
        value: {
            type: Array,
            required: false,
            default: null,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: ['update:value'],

    data() {
        return {
            items: [],
        };
    },

    watch: {
        value: {
            immediate: true,
            handler(newValue) {
                const incoming = Array.isArray(newValue) ? newValue : [];

                // Rebuild nur, wenn sich der Wert wirklich unterscheidet — verhindert eine
                // Endlosschleife mit dem eigenen update:value-Emit (und Fokus-Verlust im Feld).
                if (JSON.stringify(incoming) === JSON.stringify(this.normalize(this.items))) {
                    return;
                }

                this.items = incoming.map((item) => ({
                    name: item && typeof item.name === 'string' ? item.name : '',
                    value: item && typeof item.value === 'string' ? item.value : '',
                }));
            },
        },
    },

    methods: {
        addProperty() {
            this.items.push({ name: '', value: '' });
            this.emitChange();
        },

        removeProperty(index) {
            this.items.splice(index, 1);
            this.emitChange();
        },

        emitChange() {
            this.$emit('update:value', this.normalize(this.items));
        },

        normalize(items) {
            return items.map((item) => ({ name: item.name, value: item.value }));
        },
    },
});
