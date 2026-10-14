import './component';
import './preview';

const { Application } = Shopware;

/**
 * Nachweise als eigener CMS-Baustein: erscheint in der Baustein-Seitenleiste und ist frei
 * platzierbar. Er umschließt das Element `rc-documents` (verlinkte Dateien und `DigitalDocument`
 * aus einer Quelle).
 */
Application.getContainer('service').cmsService.registerCmsBlock({
    name: 'rc-documents',
    label: 'sw-cms.blocks.rc-documents.label',
    category: 'text',
    component: 'sw-cms-block-rc-documents',
    previewComponent: 'sw-cms-preview-rc-documents',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: 'rc-documents',
    },
});
