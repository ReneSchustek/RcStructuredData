import template from './sw-cms-el-config-rc-faq.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-config-rc-faq', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.createdComponent();
    },

    computed: {
        faqItems() {
            return this.element.config.faqItems.value;
        },

        displayMode: {
            get() {
                return this.element.config.displayMode.value;
            },
            set(value) {
                this.element.config.displayMode.value = value;
                this.$emit('element-update', this.element);
            },
        },

        displayModeOptions() {
            return [
                { value: 'accordion', label: this.$tc('sw-cms.elements.rc-faq.config.displayMode.accordion') },
                { value: 'list', label: this.$tc('sw-cms.elements.rc-faq.config.displayMode.list') },
            ];
        },
    },

    methods: {
        createdComponent() {
            this.initElementConfig('rc-faq');

            // Nebenwirkungsfreie Initialisierung außerhalb des Computed-Getters (kein State-Write
            // während des Renderns).
            if (!Array.isArray(this.element.config.faqItems.value)) {
                this.element.config.faqItems.value = [];
            }

            // Bestandselemente (vor v2.5.0) haben noch keinen displayMode — sicher nachziehen.
            if (!this.element.config.displayMode || typeof this.element.config.displayMode.value !== 'string') {
                this.element.config.displayMode = { source: 'static', value: 'accordion' };
            }
        },

        addFaqItem() {
            this.faqItems.push({ question: '', answer: '' });
            this.$emit('element-update', this.element);
        },

        removeFaqItem(index) {
            this.faqItems.splice(index, 1);
            this.$emit('element-update', this.element);
        },
    },
});
