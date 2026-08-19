const { Application, Classes: { ApiService } } = Shopware;

/**
 * Ruft den Admin-API-Endpunkt der Schema-Diagnose auf (Knotenzahl + fehlende Felder einer Kategorie).
 */
class RcSchemaDiagnoseApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'rc-schema') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'rcSchemaDiagnoseService';
    }

    diagnoseCategory(categoryId) {
        return this.httpClient
            .get(`/_action/rc-schema/diagnose/category/${categoryId}`, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    diagnoseLandingPage(landingPageId) {
        return this.httpClient
            .get(`/_action/rc-schema/diagnose/landing-page/${landingPageId}`, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }
}

Application.addServiceProvider('rcSchemaDiagnoseService', (container) => {
    const initContainer = Application.getContainer('init');

    return new RcSchemaDiagnoseApiService(initContainer.httpClient, container.loginService);
});
