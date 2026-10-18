import './component';
import './config';
import './preview';

const { Application } = Shopware;

/**
 * Begriffssatz — eine sichtbare Liste von Fachbegriffen, aus der zugleich das `DefinedTermSet`
 * entsteht.
 *
 * **Warum es dieses Element gibt:** Steht ein Begriffssatz nur als handgeschriebenes JSON-LD da,
 * liegt die Auszeichnung neben der Seite statt in ihr und ist beim ersten Umbau der Seite still
 * veraltet. Hier stammt beides aus derselben Pflege.
 */
Application.getContainer('service').cmsService.registerCmsElement({
    name: 'rc-defined-terms',
    label: 'sw-cms.elements.rc-defined-terms.label',
    component: 'sw-cms-el-rc-defined-terms',
    configComponent: 'sw-cms-el-config-rc-defined-terms',
    previewComponent: 'sw-cms-el-preview-rc-defined-terms',
    category: 'text',
    defaultConfig: {
        termSetName: {
            source: 'static',
            value: '',
        },
        termSetDescription: {
            source: 'static',
            value: '',
        },
        terms: {
            source: 'static',
            value: [],
        },
    },
});
