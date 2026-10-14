import template from './sw-cms-el-rc-documents.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-rc-documents', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('rc-documents');
        },
    },
});
