import './component';
import './config';
import './preview';

const { Application } = Shopware;

Application.getContainer('service').cmsService.registerCmsElement({
    name: 'rc-faq',
    label: 'sw-cms.elements.rc-faq.label',
    component: 'sw-cms-el-rc-faq',
    configComponent: 'sw-cms-el-config-rc-faq',
    previewComponent: 'sw-cms-el-preview-rc-faq',
    category: 'text',
    defaultConfig: {
        faqItems: {
            source: 'static',
            value: [],
        },
        displayMode: {
            source: 'static',
            value: 'accordion',
        },
    },
});
