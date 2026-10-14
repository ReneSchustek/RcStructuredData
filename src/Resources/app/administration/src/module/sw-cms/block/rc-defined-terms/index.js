import './component';
import './preview';

const { Application } = Shopware;

/**
 * Begriffssatz als eigener CMS-Baustein: erscheint in der Baustein-Seitenleiste und ist frei
 * platzierbar. Er umschließt das Element `rc-defined-terms` (Anzeige und `DefinedTermSet` aus
 * einer Quelle).
 */
Application.getContainer('service').cmsService.registerCmsBlock({
    name: 'rc-defined-terms',
    label: 'sw-cms.blocks.rc-defined-terms.label',
    category: 'text',
    component: 'sw-cms-block-rc-defined-terms',
    previewComponent: 'sw-cms-preview-rc-defined-terms',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: 'rc-defined-terms',
    },
});
