const { Application, Classes: { ApiService } } = Shopware;

const DISABLED_SETTINGS = Object.freeze({ active: false, entities: [] });

/**
 * Reads the change log switches from a dedicated admin route, so users with
 * only the change log viewer role do not need system_config:read.
 * The response is cached per page load.
 */
class MuwaChangeLogApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'muwa-change-log') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'muwaChangeLogService';
        this.settingsPromise = null;
    }

    getSettings() {
        if (!this.settingsPromise) {
            this.settingsPromise = this.fetchSettings();
        }

        return this.settingsPromise;
    }

    fetchSettings() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/settings`, { headers })
            .then((response) => ApiService.handleResponse(response))
            .then((data) => ({
                active: data?.active === true,
                entities: Array.isArray(data?.entities) ? data.entities : [],
            }))
            .catch(() => {
                // Retry on the next call instead of caching the failure.
                this.settingsPromise = null;

                return DISABLED_SETTINGS;
            });
    }

    clearSettingsCache() {
        this.settingsPromise = null;
    }

    async isEnabledFor(entityName) {
        const settings = await this.getSettings();

        return settings.active && settings.entities.includes(entityName);
    }
}

Application.addServiceProvider('muwaChangeLogService', (container) => {
    const initContainer = Application.getContainer('init');
    return new MuwaChangeLogApiService(initContainer.httpClient, container.loginService);
});
