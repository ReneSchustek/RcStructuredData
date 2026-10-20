import './component';
import './config';
import './preview';

const { Application } = Shopware;

/**
 * Nachweise: eine sichtbare Liste von Dateien (Prüfzeugnisse, Bescheide), aus der zugleich je ein
 * `DigitalDocument` entsteht.
 *
 * Steht ein Prüfzeugnis nur als Verweis in einem freien
 * Textblock, bleibt für den Nachweis allein handgeschriebenes JSON-LD. Was ein Verweis *ist*,
 * sagt ein Verweis nicht; dieses Element pflegt es ausdrücklich.
 */
Application.getContainer('service').cmsService.registerCmsElement({
    name: 'rc-documents',
    label: 'sw-cms.elements.rc-documents.label',
    component: 'sw-cms-el-rc-documents',
    configComponent: 'sw-cms-el-config-rc-documents',
    previewComponent: 'sw-cms-el-preview-rc-documents',
    category: 'text',
    defaultConfig: {
        documentsTitle: {
            source: 'static',
            value: '',
        },
        documents: {
            source: 'static',
            value: [],
        },
    },
});
