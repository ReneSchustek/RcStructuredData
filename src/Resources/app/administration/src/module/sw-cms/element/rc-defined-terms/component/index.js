import template from './sw-cms-el-rc-defined-terms.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-rc-defined-terms', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('rc-defined-terms');
        },
    },
});
