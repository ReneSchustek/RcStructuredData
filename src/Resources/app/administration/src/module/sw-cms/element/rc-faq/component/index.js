import template from './sw-cms-el-rc-faq.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-rc-faq', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('rc-faq');
        },
    },
});
