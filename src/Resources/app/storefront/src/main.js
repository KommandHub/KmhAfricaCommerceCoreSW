const PluginManager = window.PluginManager;

// African address-form enhancement: cascading division dropdown + live phone
// normalization. Registered against the wrapper the Twig override renders.
PluginManager.register(
    'KmhAddressForm',
    () => import('./kmh-address-form/kmh-address-form.plugin'),
    '[data-kmh-af-address]',
);
