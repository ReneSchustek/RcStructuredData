import template from './sw-cms-el-config-rc-documents.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-config-rc-documents', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.createdComponent();
    },

    computed: {
        documents() {
            return this.element.config.documents.value;
        },
    },

    methods: {
        createdComponent() {
            this.initElementConfig('rc-documents');

            // Nebenwirkungsfreie Initialisierung außerhalb des Computed-Getters (kein
            // State-Write während des Renderns); dieselbe Vorsicht wie bei rc-faq.
            if (!Array.isArray(this.element.config.documents.value)) {
                this.element.config.documents.value = [];
            }
        },

        addDocument() {
            this.documents.push({
                name: '',
                description: '',
                mediaId: null,
                datePublished: null,
                expires: null,
            });
            this.$emit('element-update', this.element);
        },

        removeDocument(index) {
            this.documents.splice(index, 1);
            this.$emit('element-update', this.element);
        },
    },
});
