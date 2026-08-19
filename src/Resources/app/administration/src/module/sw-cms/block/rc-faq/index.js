import './component';
import './preview';

const { Application } = Shopware;

/**
 * Dedizierter FAQ-CMS-Block: erscheint in der Block-Seitenleiste und ist frei per Drag&Drop
 * platzierbar. Er kapselt das bestehende rc-faq-Element (Anzeige + FAQPage-Schema aus einer Quelle).
 */
Application.getContainer('service').cmsService.registerCmsBlock({
    name: 'rc-faq',
    label: 'sw-cms.blocks.rc-faq.label',
    category: 'text',
    component: 'sw-cms-block-rc-faq',
    previewComponent: 'sw-cms-preview-rc-faq',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: 'rc-faq',
    },
});
