const { Application, Classes: { ApiService } } = Shopware;

class MuwaLogViewerApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'muwa-log-viewer') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'muwaLogViewerService';
    }

    listFiles() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/files`, { headers })
            .then((response) => ApiService.handleResponse(response));
    }

    getContent(file, lines = 2000, before = null) {
        const headers = this.getBasicHeaders();
        const params = { file, lines };
        if (before !== null) {
            params.before = before;
        }

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/content`, { headers, params })
            .then((response) => ApiService.handleResponse(response));
    }

    download(file) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/download`, {
                headers,
                params: { file },
                responseType: 'blob',
            })
            .then((response) => response.data);
    }
}

Application.addServiceProvider('muwaLogViewerService', (container) => {
    const initContainer = Application.getContainer('init');
    return new MuwaLogViewerApiService(initContainer.httpClient, container.loginService);
});
