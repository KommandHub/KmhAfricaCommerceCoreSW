import template from './kmh-af-reference-import.html.twig';
import './kmh-af-reference-import.scss';


const { Component, Mixin } = Shopware;

/**
 * Config-page component: a button that runs the reference-data import via the
 * admin action, then shows the per-dataset reconcile counts. Referenced from
 * config.xml as <component name="kmh-af-reference-import">.
 */
Component.register('kmh-af-reference-import', {
    template,

    inject: ['kmhImportApiService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: false,
            sections: null,
        };
    },

    computed: {
        rows() {
            if (!this.sections) {
                return [];
            }

            return Object.keys(this.sections).map((dataset) => ({
                dataset,
                ...this.sections[dataset],
            }));
        },
    },

    methods: {
        runImport() {
            this.isLoading = true;
            this.sections = null;

            this.kmhImportApiService.runImport()
                .then((response) => {
                    this.sections = response.sections;
                    this.createNotificationSuccess({ message: this.$tc('kmhAf.import.success') });
                })
                .catch((error) => {
                    const code = error?.response?.data?.errors?.[0]?.code;
                    const key = code === 'KMH_AF__REFERENCE_DATA_DISABLED' ? 'kmhAf.import.disabled' : 'kmhAf.import.error';
                    this.createNotificationError({ message: this.$tc(key) });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
    },
});
