import KmhImportApiService from './service/kmh-import.api.service';
import './component/kmh-af-reference-import';

import enGB from './snippet/en-GB.json';
import deDE from './snippet/de-DE.json';

const { Application, Locale } = Shopware;

Application.addServiceProvider('kmhImportApiService', (container) => {
    const initContainer = Application.getContainer('init');
    return new KmhImportApiService(initContainer.httpClient, container.loginService);
});

Locale.extend('en-GB', enGB);
Locale.extend('de-DE', deDE);
