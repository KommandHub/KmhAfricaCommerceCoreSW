const ApiService = Shopware.Classes.ApiService;

/**
 * Calls the plugin's admin action that runs the reference-data import, so the
 * config-page button doesn't need the CLI.
 */
export default class KmhImportApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'kmh-af') {
        super(httpClient, loginService, apiEndpoint);
    }

    runImport() {
        return this.httpClient
            .post('/_action/kmh-af/reference-import', {}, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }
}
