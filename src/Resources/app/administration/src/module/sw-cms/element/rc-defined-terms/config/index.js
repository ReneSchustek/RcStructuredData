import template from './sw-cms-el-config-rc-defined-terms.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-config-rc-defined-terms', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.createdComponent();
    },

    computed: {
        terms() {
            return this.element.config.terms.value;
        },
    },

    methods: {
        createdComponent() {
            this.initElementConfig('rc-defined-terms');

            // Nebenwirkungsfreie Initialisierung außerhalb des Computed-Getters (kein
            // State-Write während des Renderns), dieselbe Vorsicht wie bei rc-faq.
            if (!Array.isArray(this.element.config.terms.value)) {
                this.element.config.terms.value = [];
            }
        },

        addTerm() {
            this.terms.push({ term: '', definition: '' });
            this.$emit('element-update', this.element);
        },

        removeTerm(index) {
            this.terms.splice(index, 1);
            this.$emit('element-update', this.element);
        },
    },
});
